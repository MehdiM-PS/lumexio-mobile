/*
 * Lumexio native charts — the drawing/interaction engine that runs inside
 * the chart web view. Reads its whole configuration from the inlined
 * `window.__LUMEXIO_CHART__` object (see ChartDocument) and never talks back
 * to PHP: every interaction is resolved here so a touch never waits on a
 * bridge round-trip.
 *
 * Written deliberately plain (var, no optional chaining/nullish coalescing,
 * no ResizeObserver without a fallback): Android's System WebView can be
 * several years behind the device's OS version, and a syntax error there
 * would blank the chart on exactly the devices we can't test on.
 */
(function () {
    'use strict';

    var C = window.__LUMEXIO_CHART__ || {};
    var host = document.getElementById('chart');
    var tip = document.getElementById('tip');
    var legendEl = document.getElementById('legend');

    var PALETTE = C.palette && C.palette.length ? C.palette : ['#2D5D5A', '#EC7C0E', '#36A558', '#E24947', '#6D6F78'];
    var COLORS = C.colors || {};
    var TEXT = COLORS.text || '#14151A';
    var MUTED = COLORS.muted || '#6D6F78';
    var GRID = COLORS.grid || 'rgba(17,17,17,0.10)';
    var TIP_BG = COLORS.tooltipBg || '#14151A';
    var TIP_TEXT = COLORS.tooltipText || '#FFFFFF';

    var TYPE = C.type || 'line';
    var IS_PIE = TYPE === 'pie' || TYPE === 'donut';
    var IS_HBAR = TYPE === 'horizontal-bar';
    var IS_BAR = TYPE === 'bar' || TYPE === 'stacked-bar' || IS_HBAR;
    var IS_STACKED = TYPE === 'stacked-bar';
    var CAN_ZOOM = C.zoom !== false && (TYPE === 'line' || TYPE === 'area');

    var LABELS = C.labels || [];
    var DECIMALS = typeof C.decimals === 'number' ? C.decimals : 0;
    var UNIT = C.unit || '';

    // ---------------------------------------------------------------- data

    function toNumber(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }
        var n = Number(value);

        return isFinite(n) ? n : null;
    }

    var SERIES = (C.series || []).map(function (s, i) {
        return {
            name: s.name || '',
            data: (s.data || []).map(toNumber),
            notes: s.notes || [],
            color: s.color || PALETTE[i % PALETTE.length],
            dashed: !!s.dashed,
            fill: s.fill === undefined ? TYPE === 'area' : !!s.fill
        };
    });

    var COUNT = SERIES.reduce(function (max, s) {
        return Math.max(max, s.data.length);
    }, LABELS.length);

    var state = {
        hidden: {},
        focus: typeof C.focus === 'number' && C.focus >= 0 ? C.focus : -1,
        view: null, // [startIndex, endIndex] float window; null = full range
        entered: false
    };

    function visibleSeries() {
        return SERIES.filter(function (s, i) {
            return !state.hidden[i];
        });
    }

    /** Slices of a pie/donut come from the first series, one per label. */
    function pieSlices() {
        var data = SERIES.length ? SERIES[0].data : [];

        return data.map(function (value, i) {
            return {
                index: i,
                value: value === null ? 0 : Math.max(0, value),
                label: LABELS[i] !== undefined ? LABELS[i] : 'Part ' + (i + 1),
                color: PALETTE[i % PALETTE.length]
            };
        }).filter(function (slice) {
            return !state.hidden[slice.index];
        });
    }

    function hasAnyValue() {
        return SERIES.some(function (s) {
            return s.data.some(function (v) {
                return v !== null;
            });
        });
    }

    // ----------------------------------------------------------- formatting

    function formatValue(value) {
        if (value === null || value === undefined) {
            return '—';
        }

        var fixed = Math.abs(value).toFixed(DECIMALS);
        var parts = fixed.split('.');
        // Narrow no-break space as the thousands separator — the French
        // convention, and it never wraps mid-number in the tooltip.
        var whole = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        var out = (value < 0 ? '-' : '') + whole + (parts[1] ? ',' + parts[1] : '');

        return out + UNIT;
    }

    /** Short axis form: 12 500 € becomes 12,5k so the gutter stays narrow. */
    function formatAxis(value) {
        var abs = Math.abs(value);
        if (abs >= 1000000) {
            return shortenTick(value / 1000000) + 'M';
        }
        if (abs >= 1000) {
            return shortenTick(value / 1000) + 'k';
        }

        // Only spell out a decimal when the tick actually has one — a
        // "0,0" baseline under a two-decimal series reads as noise.
        return Math.abs(value % 1) > 0.001 ? value.toFixed(1).replace('.', ',') : String(Math.round(value));
    }

    /** One decimal, but only when the scaled tick actually needs it. */
    function shortenTick(scaled) {
        return Math.abs(scaled % 1) < 0.05
            ? String(Math.round(scaled))
            : scaled.toFixed(1).replace('.', ',');
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"]/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch];
        });
    }

    /**
     * Coordinates are rounded to a tenth of a pixel before they go into the
     * SVG. Sub-pixel precision is invisible on a phone, and the raw floats
     * would otherwise add kilobytes to a document that crosses the bridge on
     * every render of the hosting screen.
     */
    function r(value) {
        return Math.round(value * 10) / 10;
    }

    // --------------------------------------------------------------- scales

    /** Round a raw axis step up to a 1/2/5 × 10ⁿ step so ticks read cleanly. */
    function niceStep(raw) {
        if (raw <= 0) {
            return 1;
        }
        var exponent = Math.floor(Math.log(raw) / Math.LN10);
        var magnitude = Math.pow(10, exponent);
        var fraction = raw / magnitude;
        var nice = fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 5 ? 5 : 10;

        return nice * magnitude;
    }

    /**
     * Value-axis domain. Bars are always anchored at zero (a floating bar
     * baseline misrepresents magnitude); lines get a 6% headroom pad and
     * only drop the zero anchor when the whole series sits well above it.
     */
    function valueDomain() {
        var series = visibleSeries();
        var max = 0;
        var min = 0;

        if (IS_STACKED) {
            for (var i = 0; i < COUNT; i++) {
                var sum = 0;
                series.forEach(function (s) {
                    sum += s.data[i] === null || s.data[i] === undefined ? 0 : s.data[i];
                });
                max = Math.max(max, sum);
                min = Math.min(min, sum);
            }
        } else {
            series.forEach(function (s) {
                s.data.forEach(function (v) {
                    if (v === null) {
                        return;
                    }
                    max = Math.max(max, v);
                    min = Math.min(min, v);
                });
            });
        }

        if (max === min) {
            max = min + 1;
        }

        if (!IS_BAR) {
            var pad = (max - min) * 0.06;
            max += pad;
            if (min < 0) {
                min -= pad;
            }
        }

        var step = niceStep((max - min) / 4);

        return {
            min: min < 0 ? Math.floor(min / step) * step : 0,
            max: Math.ceil(max / step) * step,
            step: step
        };
    }

    function viewWindow() {
        if (!state.view) {
            return [0, Math.max(0, COUNT - 1)];
        }

        return state.view;
    }

    // -------------------------------------------------------------- drawing

    var SMOOTHING = 0.2; // Catmull-Rom control-point pull ≈ Chart.js tension 0.35

    function smoothPath(points) {
        if (!points.length) {
            return '';
        }
        if (points.length === 1) {
            return 'M' + points[0].x + ' ' + points[0].y;
        }

        var d = 'M' + points[0].x + ' ' + points[0].y;

        for (var i = 0; i < points.length - 1; i++) {
            var p0 = points[i === 0 ? 0 : i - 1];
            var p1 = points[i];
            var p2 = points[i + 1];
            var p3 = points[i + 2 < points.length ? i + 2 : i + 1];

            var c1x = r(p1.x + (p2.x - p0.x) * SMOOTHING);
            var c1y = r(p1.y + (p2.y - p0.y) * SMOOTHING);
            var c2x = r(p2.x - (p3.x - p1.x) * SMOOTHING);
            var c2y = r(p2.y - (p3.y - p1.y) * SMOOTHING);

            d += 'C' + c1x + ' ' + c1y + ',' + c2x + ' ' + c2y + ',' + p2.x + ' ' + p2.y;
        }

        return d;
    }

    /** Split a null-punctuated series into the runs that are actually drawn. */
    function segments(points) {
        var runs = [];
        var current = [];

        points.forEach(function (p) {
            if (p === null) {
                if (current.length) {
                    runs.push(current);
                }
                current = [];

                return;
            }
            current.push(p);
        });

        if (current.length) {
            runs.push(current);
        }

        return runs;
    }

    var geom = null; // last computed plot geometry, reused by hit-testing

    function drawCartesian(width, height) {
        var domain = valueDomain();
        var view = viewWindow();
        var span = Math.max(1e-6, view[1] - view[0]);

        var ticks = [];
        for (var t = domain.min; t <= domain.max + domain.step * 0.001; t += domain.step) {
            ticks.push(t);
        }

        // Category labels are centred on their slot, so the first and last
        // of them need half their width of margin or they run off the
        // canvas. On the value-axis side that margin competes with the tick
        // gutter — the wider of the two wins.
        // Widest tick, not just the largest value: a domain that dips below
        // zero puts its longest label ("-500") at the bottom of the axis.
        var widestTick = ticks.reduce(function (widest, tick) {
            return Math.max(widest, textWidth(formatAxis(tick)));
        }, 0);
        var gutter = IS_HBAR
            ? Math.min(110, 8 + textWidth(longestLabelText()))
            : Math.max(24, widestTick + 8);
        var edgeLeft = IS_HBAR || IS_BAR ? 0 : textWidth(LABELS[0]) / 2;
        var edgeRight = IS_HBAR ? 8 : textWidth(LABELS[COUNT - 1]) / 2;
        var padLeft = Math.max(gutter, edgeLeft);
        var padRight = Math.max(8, edgeRight);
        var padTop = 10;
        var padBottom = IS_HBAR ? 18 : 20;

        var plotW = Math.max(1, width - padLeft - padRight);
        var plotH = Math.max(1, height - padTop - padBottom);

        // `value` maps a data value onto the value axis (y for vertical
        // charts, x for horizontal ones); `slot` maps a category index onto
        // the category axis.
        function value(v) {
            var ratio = (v - domain.min) / (domain.max - domain.min);

            return r(IS_HBAR ? padLeft + ratio * plotW : padTop + (1 - ratio) * plotH);
        }

        function slot(index) {
            if (IS_HBAR) {
                return r(padTop + ((index + 0.5) / Math.max(1, COUNT)) * plotH);
            }
            if (IS_BAR) {
                return r(padLeft + ((index + 0.5) / Math.max(1, COUNT)) * plotW);
            }

            return r(padLeft + ((index - view[0]) / span) * plotW);
        }

        geom = {
            padLeft: padLeft,
            padTop: padTop,
            plotW: plotW,
            plotH: plotH,
            slot: slot,
            value: value,
            view: view,
            span: span
        };

        var svg = '';

        if (C.grid !== false) {
            ticks.forEach(function (tick) {
                var p = value(tick);
                svg += IS_HBAR
                    ? '<line x1="' + p + '" y1="' + padTop + '" x2="' + p + '" y2="' + (padTop + plotH) + '" stroke="' + GRID + '" stroke-width="1"/>'
                    : '<line x1="' + padLeft + '" y1="' + p + '" x2="' + (padLeft + plotW) + '" y2="' + p + '" stroke="' + GRID + '" stroke-width="1"/>';
            });
        }

        // Value-axis labels
        ticks.forEach(function (tick) {
            var p = value(tick);
            svg += IS_HBAR
                ? '<text class="ax" x="' + p + '" y="' + (padTop + plotH + 13) + '" text-anchor="middle">' + escapeHtml(formatAxis(tick)) + '</text>'
                : '<text class="ax" x="' + (padLeft - 6) + '" y="' + (p + 3.5) + '" text-anchor="end">' + escapeHtml(formatAxis(tick)) + '</text>';
        });

        if (IS_HBAR) {
            svg += drawHorizontalBars(value, slot, domain);
        } else if (IS_BAR) {
            svg += drawBars(value, slot, domain);
        } else {
            svg += drawLines(value, slot, domain);
        }

        svg += drawCategoryLabels(slot, padTop, plotH, padLeft, plotW, view, span);
        svg += drawFocus(value, slot, view, span, padTop, plotH);

        return svg;
    }

    function longestLabelText() {
        return LABELS.reduce(function (longest, label) {
            return String(label).length > longest.length ? String(label) : longest;
        }, '');
    }

    /**
     * Approximate rendered width of a label at the 10px axis size. Measuring
     * for real would mean a layout pass per label per frame; 5.4px per
     * character is close enough for digits and short date labels, which is
     * all the axes ever carry.
     */
    function textWidth(text) {
        return text === undefined || text === null ? 0 : String(text).length * 5.4;
    }

    function drawLines(value, slot, domain) {
        var svg = '';

        SERIES.forEach(function (s, si) {
            if (state.hidden[si]) {
                return;
            }

            var points = [];
            for (var i = 0; i < COUNT; i++) {
                var v = s.data[i];
                points.push(v === null || v === undefined ? null : { x: slot(i), y: value(v) });
            }

            segments(points).forEach(function (run) {
                var d = smoothPath(run);

                if (s.fill && run.length > 1) {
                    var base = value(Math.max(0, domain.min));
                    var area = d + 'L' + run[run.length - 1].x + ' ' + base + 'L' + run[0].x + ' ' + base + 'Z';
                    svg += '<path class="fill" d="' + area + '" fill="' + s.color + '" fill-opacity="0.15"/>';
                }

                svg += '<path class="ln" d="' + d + '" fill="none" stroke="' + s.color + '" stroke-width="2.5"'
                    + ' stroke-linecap="round" stroke-linejoin="round"'
                    + (s.dashed ? ' stroke-dasharray="6 4"' : '') + '/>';

                if (run.length === 1) {
                    svg += '<circle cx="' + run[0].x + '" cy="' + run[0].y + '" r="3.5" fill="' + s.color + '"/>';
                }
            });
        });

        return svg;
    }

    function drawBars(value, slot, domain) {
        var series = visibleSeries();
        if (!series.length) {
            return '';
        }

        var slotWidth = geom.plotW / Math.max(1, COUNT);
        var groupWidth = slotWidth * 0.68;
        var barWidth = IS_STACKED ? groupWidth : groupWidth / series.length;
        var zero = value(Math.max(domain.min, 0));
        var svg = '';

        for (var i = 0; i < COUNT; i++) {
            var center = slot(i);
            var stackTop = zero;

            series.forEach(function (s, si) {
                var v = s.data[i];
                if (v === null || v === undefined) {
                    return;
                }

                var x = r(IS_STACKED ? center - barWidth / 2 : center - groupWidth / 2 + si * barWidth);
                var y;
                var h;

                if (IS_STACKED) {
                    h = r(Math.abs(zero - value(v)));
                    y = r(stackTop - h);
                    stackTop = y;
                } else {
                    y = Math.min(zero, value(v));
                    h = r(Math.abs(zero - value(v)));
                }

                var dim = state.focus >= 0 && state.focus !== i ? ' opacity="0.45"' : '';
                svg += '<rect class="br" x="' + x + '" y="' + y + '" width="' + r(Math.max(1, barWidth - 1.5))
                    + '" height="' + Math.max(1, h) + '" rx="3" fill="' + s.color + '"' + dim + '/>';
            });
        }

        return svg;
    }

    function drawHorizontalBars(value, slot, domain) {
        var series = visibleSeries();
        if (!series.length) {
            return '';
        }

        var slotHeight = geom.plotH / Math.max(1, COUNT);
        var groupHeight = slotHeight * 0.68;
        var barHeight = IS_STACKED ? groupHeight : groupHeight / series.length;
        var zero = value(Math.max(domain.min, 0));
        var svg = '';

        for (var i = 0; i < COUNT; i++) {
            var center = slot(i);
            var stackLeft = zero;

            series.forEach(function (s, si) {
                var v = s.data[i];
                if (v === null || v === undefined) {
                    return;
                }

                var y = r(IS_STACKED ? center - barHeight / 2 : center - groupHeight / 2 + si * barHeight);
                var w = r(Math.abs(value(v) - zero));
                var x = r(IS_STACKED ? stackLeft : Math.min(zero, value(v)));
                if (IS_STACKED) {
                    stackLeft += w;
                }

                var dim = state.focus >= 0 && state.focus !== i ? ' opacity="0.45"' : '';
                svg += '<rect class="br" x="' + x + '" y="' + y + '" width="' + Math.max(1, w)
                    + '" height="' + r(Math.max(1, barHeight - 1.5)) + '" rx="3" fill="' + s.color + '"' + dim + '/>';
            });
        }

        return svg;
    }

    function drawCategoryLabels(slot, padTop, plotH, padLeft, plotW, view, span) {
        if (!LABELS.length) {
            return '';
        }

        var svg = '';

        if (IS_HBAR) {
            for (var i = 0; i < COUNT; i++) {
                if (LABELS[i] === undefined) {
                    continue;
                }
                svg += '<text class="ax" x="' + (padLeft - 6) + '" y="' + (slot(i) + 3.5) + '" text-anchor="end">'
                    + escapeHtml(LABELS[i]) + '</text>';
            }

            return svg;
        }

        // Thin the labels down to what fits: one every Nth slot, plus the
        // last one so the axis always states where the window ends.
        var visible = IS_BAR ? COUNT : Math.ceil(span) + 1;
        var maxLabels = Math.max(2, Math.floor(plotW / Math.max(28, textWidth(longestLabelText()) + 10)));
        var stride = Math.max(1, Math.ceil(visible / maxLabels));
        var first = IS_BAR ? 0 : Math.max(0, Math.floor(view[0]));
        var last = IS_BAR ? COUNT - 1 : Math.min(COUNT - 1, Math.ceil(view[1]));

        var candidates = [];
        for (var j = first; j <= last; j += stride) {
            candidates.push(j);
        }
        if (candidates[candidates.length - 1] !== last) {
            candidates.push(last);
        }

        // The forced last label can land right on top of its stride-picked
        // neighbour (a 30-point window whose stride doesn't divide evenly).
        // Walking from the right and dropping anything that crowds what is
        // already placed keeps the end of the range and removes the
        // collision instead.
        var placed = [];
        for (var k = candidates.length - 1; k >= 0; k--) {
            var index = candidates[k];

            if (LABELS[index] === undefined) {
                continue;
            }

            var x = slot(index);
            if (x < padLeft - 2 || x > padLeft + plotW + 2) {
                continue;
            }

            var half = textWidth(LABELS[index]) / 2;
            var previous = placed[placed.length - 1];
            if (previous && previous.x - previous.half - (x + half) < 6) {
                continue;
            }

            placed.push({ index: index, x: x, half: half });
        }

        placed.forEach(function (label) {
            svg += '<text class="ax" x="' + label.x + '" y="' + (padTop + plotH + 14) + '" text-anchor="middle">'
                + escapeHtml(LABELS[label.index]) + '</text>';
        });

        return svg;
    }

    /** Crosshair + point markers for the focused category index. */
    function drawFocus(value, slot, view, span, padTop, plotH) {
        if (state.focus < 0 || state.focus >= COUNT || IS_BAR) {
            return '';
        }
        if (!IS_BAR && (state.focus < view[0] - 0.5 || state.focus > view[1] + 0.5)) {
            return '';
        }

        var x = slot(state.focus);
        var svg = '<line x1="' + x + '" y1="' + padTop + '" x2="' + x + '" y2="' + (padTop + plotH)
            + '" stroke="' + MUTED + '" stroke-opacity="0.45" stroke-width="1"/>';

        SERIES.forEach(function (s, si) {
            if (state.hidden[si]) {
                return;
            }
            var v = s.data[state.focus];
            if (v === null || v === undefined) {
                return;
            }
            var y = value(v);
            svg += '<circle cx="' + x + '" cy="' + y + '" r="4.5" fill="' + s.color + '"/>'
                + '<circle cx="' + x + '" cy="' + y + '" r="1.8" fill="#FFFFFF"/>';
        });

        return svg;
    }

    function drawPie(width, height) {
        var slices = pieSlices();
        var total = slices.reduce(function (sum, slice) {
            return sum + slice.value;
        }, 0);

        if (total <= 0) {
            return '';
        }

        var cx = r(width / 2);
        var cy = r(height / 2);
        var radius = r(Math.max(10, Math.min(width, height) / 2 - 10));
        var inner = TYPE === 'donut' ? r(radius * 0.58) : 0;
        var angle = -Math.PI / 2;
        var svg = '';

        slices.forEach(function (slice) {
            var sweep = (slice.value / total) * Math.PI * 2;
            var focused = state.focus === slice.index;
            // Not `r` — that name belongs to the rounding helper, and a
            // `var` here would shadow it for the whole function.
            var outer = focused ? radius : radius - 4;
            var mid = angle + sweep / 2;
            var offset = focused ? 5 : 0;
            var ox = r(cx + Math.cos(mid) * offset);
            var oy = r(cy + Math.sin(mid) * offset);

            svg += '<path class="sl" d="' + arcPath(ox, oy, outer, inner, angle, angle + sweep) + '" fill="' + slice.color + '"/>';

            angle += sweep;
        });

        if (TYPE === 'donut') {
            var focusedSlice = null;
            slices.forEach(function (slice) {
                if (slice.index === state.focus) {
                    focusedSlice = slice;
                }
            });

            var headline = focusedSlice ? formatValue(focusedSlice.value) : formatValue(total);
            var caption = focusedSlice ? focusedSlice.label : (C.totalLabel || 'Total');

            svg += '<text class="ct" x="' + cx + '" y="' + (cy + 1) + '" text-anchor="middle">' + escapeHtml(headline) + '</text>'
                + '<text class="cs" x="' + cx + '" y="' + (cy + 16) + '" text-anchor="middle">' + escapeHtml(caption) + '</text>';
        }

        geom = { cx: cx, cy: cy, radius: radius, inner: inner, total: total, slices: slices };

        return svg;
    }

    /** Ring/pie wedge. `inner` of 0 produces a solid pie slice. */
    function arcPath(cx, cy, outer, inner, from, to) {
        // A full circle can't be expressed as a single arc — nudge the end
        // back a hair so the two-arc form still closes visually.
        if (to - from >= Math.PI * 2) {
            to = from + Math.PI * 2 - 0.0001;
        }

        var large = to - from > Math.PI ? 1 : 0;
        var x1 = r(cx + Math.cos(from) * outer);
        var y1 = r(cy + Math.sin(from) * outer);
        var x2 = r(cx + Math.cos(to) * outer);
        var y2 = r(cy + Math.sin(to) * outer);

        if (inner <= 0) {
            return 'M' + cx + ' ' + cy + 'L' + x1 + ' ' + y1
                + 'A' + outer + ' ' + outer + ' 0 ' + large + ' 1 ' + x2 + ' ' + y2 + 'Z';
        }

        var x3 = r(cx + Math.cos(to) * inner);
        var y3 = r(cy + Math.sin(to) * inner);
        var x4 = r(cx + Math.cos(from) * inner);
        var y4 = r(cy + Math.sin(from) * inner);

        return 'M' + x1 + ' ' + y1
            + 'A' + outer + ' ' + outer + ' 0 ' + large + ' 1 ' + x2 + ' ' + y2
            + 'L' + x3 + ' ' + y3
            + 'A' + inner + ' ' + inner + ' 0 ' + large + ' 0 ' + x4 + ' ' + y4 + 'Z';
    }

    // --------------------------------------------------------------- render

    function render() {
        var width = host.clientWidth || 300;
        var height = host.clientHeight || 160;

        if (!hasAnyValue()) {
            host.innerHTML = '<div class="empty">' + escapeHtml(C.emptyText || 'Aucune donnée') + '</div>';
            renderLegend();

            return;
        }

        var body = IS_PIE ? drawPie(width, height) : drawCartesian(width, height);
        var enter = state.entered ? '' : ' enter';

        host.innerHTML = '<svg class="cv' + enter + '" width="100%" height="100%" viewBox="0 0 ' + width + ' ' + height
            + '" role="img" aria-label="' + escapeHtml(C.summary || '') + '">' + body + '</svg>';

        state.entered = true;
        renderLegend();
        renderTooltip();
    }

    function renderLegend() {
        if (!legendEl) {
            return;
        }

        var entries = IS_PIE
            ? LABELS.map(function (label, i) {
                return { key: i, name: label, color: PALETTE[i % PALETTE.length] };
            })
            : SERIES.map(function (s, i) {
                // A single unnamed series needs no legend at all; several
                // unnamed ones still need telling apart.
                return { key: i, name: s.name || 'Série ' + (i + 1), color: s.color };
            });

        var show = C.legend === true || (C.legend !== false && entries.length > 1);

        if (!show) {
            legendEl.innerHTML = '';
            legendEl.hidden = true;

            return;
        }

        legendEl.hidden = false;
        legendEl.innerHTML = entries.map(function (entry) {
            var off = state.hidden[entry.key] ? ' off' : '';

            return '<button type="button" class="lg' + off + '" data-key="' + entry.key + '">'
                + '<i style="background:' + entry.color + '"></i>' + escapeHtml(entry.name) + '</button>';
        }).join('');
    }

    function renderTooltip() {
        if (!tip) {
            return;
        }

        if (state.focus < 0 || state.focus >= COUNT) {
            tip.hidden = true;

            return;
        }

        var title;
        var rows;

        if (IS_PIE) {
            var slice = null;
            pieSlices().forEach(function (candidate) {
                if (candidate.index === state.focus) {
                    slice = candidate;
                }
            });

            if (!slice || !geom || !geom.total) {
                tip.hidden = true;

                return;
            }

            title = slice.label;
            rows = '<div class="r"><i style="background:' + slice.color + '"></i><span>'
                + escapeHtml(formatValue(slice.value)) + '</span><b>'
                + Math.round((slice.value / geom.total) * 100) + '%</b></div>';
        } else {
            title = LABELS[state.focus] !== undefined ? LABELS[state.focus] : '';
            rows = SERIES.map(function (s, si) {
                if (state.hidden[si]) {
                    return '';
                }
                var v = s.data[state.focus];
                if (v === null || v === undefined) {
                    return '';
                }
                var note = s.notes && s.notes[state.focus] ? ' <em>' + escapeHtml(s.notes[state.focus]) + '</em>' : '';

                return '<div class="r"><i style="background:' + s.color + '"></i><span>'
                    + (s.name ? escapeHtml(s.name) + ' ' : '') + '</span><b>' + escapeHtml(formatValue(v)) + '</b>' + note + '</div>';
            }).join('');

            if (!rows) {
                tip.hidden = true;

                return;
            }
        }

        tip.innerHTML = '<strong>' + escapeHtml(title) + '</strong>' + rows;
        tip.hidden = false;
        positionTooltip();
    }

    function positionTooltip() {
        var anchorX;

        if (IS_PIE) {
            anchorX = geom ? geom.cx : host.clientWidth / 2;
        } else if (geom) {
            anchorX = geom.slot(state.focus);
        } else {
            anchorX = host.clientWidth / 2;
        }

        var width = tip.offsetWidth || 120;
        var left = Math.min(Math.max(4, anchorX - width / 2), Math.max(4, host.clientWidth - width - 4));
        tip.style.left = left + 'px';

        // The tooltip lives at the top of the plot, where a high data point
        // would sit right underneath it. Drop it to the bottom in that case
        // so the marker it describes stays visible.
        var height = tip.offsetHeight || 48;
        tip.style.top = topmostFocusY() < height + 8 ? 'auto' : '2px';
        tip.style.bottom = topmostFocusY() < height + 8 ? '2px' : 'auto';
    }

    /** Y of the highest visible point at the focused index, in plot pixels. */
    function topmostFocusY() {
        // Horizontal bars map values onto X, not Y — there is no "top" to
        // dodge, so the tooltip stays put.
        if (IS_PIE || IS_HBAR || !geom || !geom.value) {
            return Infinity;
        }

        var top = Infinity;
        SERIES.forEach(function (s, si) {
            if (state.hidden[si]) {
                return;
            }
            var v = s.data[state.focus];
            if (v !== null && v !== undefined) {
                top = Math.min(top, geom.value(v));
            }
        });

        return top;
    }

    // ---------------------------------------------------------- interaction

    /** Category index nearest to a client-x coordinate inside the plot. */
    function indexAt(clientX) {
        var rect = host.getBoundingClientRect();
        var x = clientX - rect.left;

        if (!geom) {
            return -1;
        }

        if (IS_BAR && !IS_HBAR) {
            var ratio = (x - geom.padLeft) / geom.plotW;

            return clamp(Math.floor(ratio * COUNT), 0, COUNT - 1);
        }

        var raw = geom.view[0] + ((x - geom.padLeft) / geom.plotW) * geom.span;

        return clamp(Math.round(raw), 0, COUNT - 1);
    }

    /** Row index nearest to a client-y coordinate, for horizontal bars. */
    function rowAt(clientY) {
        var rect = host.getBoundingClientRect();
        var y = clientY - rect.top;

        if (!geom) {
            return -1;
        }

        return clamp(Math.floor(((y - geom.padTop) / geom.plotH) * COUNT), 0, COUNT - 1);
    }

    /** Slice under a point, or -1 outside the ring. */
    function sliceAt(clientX, clientY) {
        var rect = host.getBoundingClientRect();
        var dx = clientX - rect.left - geom.cx;
        var dy = clientY - rect.top - geom.cy;
        var distance = Math.sqrt(dx * dx + dy * dy);

        if (distance > geom.radius + 6 || distance < geom.inner) {
            return -1;
        }

        var angle = Math.atan2(dy, dx);
        // Slices are laid out clockwise from 12 o'clock; atan2 starts at 3
        // o'clock and wraps at ±π, so rotate a quarter turn and unwrap.
        var normalized = angle + Math.PI / 2;
        if (normalized < 0) {
            normalized += Math.PI * 2;
        }

        var cursor = 0;
        var found = -1;

        geom.slices.forEach(function (slice) {
            var sweep = (slice.value / geom.total) * Math.PI * 2;
            if (found === -1 && normalized >= cursor && normalized < cursor + sweep) {
                found = slice.index;
            }
            cursor += sweep;
        });

        return found;
    }

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function setFocus(index) {
        if (state.focus === index) {
            return;
        }
        state.focus = index;
        render();
    }

    var pointers = {};
    var pinch = null;
    var lastTap = 0;

    host.addEventListener('pointerdown', function (event) {
        pointers[event.pointerId] = { x: event.clientX, y: event.clientY };
        var ids = Object.keys(pointers);

        if (ids.length === 2 && CAN_ZOOM) {
            pinch = pinchSnapshot(ids);

            return;
        }

        if (ids.length > 1) {
            return;
        }

        var now = Date.now();
        if (now - lastTap < 300 && CAN_ZOOM) {
            state.view = null;
            lastTap = 0;
            render();

            return;
        }
        lastTap = now;

        focusFromPointer(event);
    });

    host.addEventListener('pointermove', function (event) {
        if (!pointers[event.pointerId]) {
            return;
        }
        pointers[event.pointerId] = { x: event.clientX, y: event.clientY };

        var ids = Object.keys(pointers);

        if (ids.length >= 2 && pinch) {
            applyPinch(ids);

            return;
        }

        if (ids.length === 1) {
            focusFromPointer(event);
        }
    });

    function releasePointer(event) {
        delete pointers[event.pointerId];
        if (Object.keys(pointers).length < 2) {
            pinch = null;
        }
    }

    host.addEventListener('pointerup', releasePointer);
    host.addEventListener('pointercancel', releasePointer);
    host.addEventListener('pointerleave', releasePointer);

    function focusFromPointer(event) {
        if (IS_PIE) {
            var slice = sliceAt(event.clientX, event.clientY);
            setFocus(slice === state.focus ? -1 : slice);

            return;
        }

        setFocus(IS_HBAR ? rowAt(event.clientY) : indexAt(event.clientX));
    }

    function pinchSnapshot(ids) {
        var a = pointers[ids[0]];
        var b = pointers[ids[1]];
        var view = viewWindow();

        return {
            distance: Math.max(1, Math.abs(a.x - b.x)),
            centerX: (a.x + b.x) / 2,
            view: [view[0], view[1]]
        };
    }

    function applyPinch(ids) {
        var a = pointers[ids[0]];
        var b = pointers[ids[1]];
        var distance = Math.max(1, Math.abs(a.x - b.x));
        var centerX = (a.x + b.x) / 2;

        var scale = pinch.distance / distance;
        var span = pinch.view[1] - pinch.view[0];
        // Never zoom past two visible points — a one-point window has no
        // line left to look at.
        var newSpan = clamp(span * scale, 1, Math.max(1, COUNT - 1));

        var rect = host.getBoundingClientRect();
        var anchorRatio = clamp((pinch.centerX - rect.left - geom.padLeft) / geom.plotW, 0, 1);
        var anchorIndex = pinch.view[0] + anchorRatio * span;

        var panIndex = ((centerX - pinch.centerX) / Math.max(1, geom.plotW)) * newSpan;
        var start = anchorIndex - anchorRatio * newSpan - panIndex;

        start = clamp(start, 0, Math.max(0, COUNT - 1 - newSpan));
        state.view = [start, start + newSpan];
        render();
    }

    if (legendEl) {
        legendEl.addEventListener('click', function (event) {
            var button = event.target.closest ? event.target.closest('.lg') : null;
            if (!button) {
                return;
            }

            var key = Number(button.getAttribute('data-key'));
            if (state.hidden[key]) {
                delete state.hidden[key];
            } else {
                state.hidden[key] = true;
            }

            render();
        });
    }

    // ---------------------------------------------------------------- boot

    // Theme colors reach the stylesheet through custom properties rather
    // than inline styles, so the axis text, tooltip and legend all follow
    // the app's current appearance from one place.
    var rootStyle = document.documentElement.style;
    rootStyle.setProperty('--text', TEXT);
    rootStyle.setProperty('--muted', MUTED);
    rootStyle.setProperty('--grid', GRID);
    rootStyle.setProperty('--tip-bg', TIP_BG);
    rootStyle.setProperty('--tip-text', TIP_TEXT);

    render();

    if (window.ResizeObserver) {
        new window.ResizeObserver(function () {
            render();
        }).observe(host);
    } else {
        window.addEventListener('resize', render);
    }
})();
