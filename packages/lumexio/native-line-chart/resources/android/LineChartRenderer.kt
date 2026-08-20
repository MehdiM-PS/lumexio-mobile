package com.lumexio.plugins.linechart.ui

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.Fill
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.input.pointer.pointerInput
import com.nativephp.mobile.ui.nativerender.NativeUIBridge
import com.nativephp.mobile.ui.nativerender.NativeUINode
import org.json.JSONArray
import org.json.JSONObject
import kotlin.math.roundToInt

/**
 * Draws the two null-padded series ("historical" solid + filled, "forecast"
 * dashed + filled) as smooth curves, mirroring the web app's Chart.js
 * config (tension 0.35, ~15%/12% fill opacity) with hand-rolled Compose
 * Canvas drawing — no charting library dependency needed for one chart.
 */
object LineChartRenderer {

    private data class Series(val historical: List<Float?>, val forecast: List<Float?>)

    private fun parseSeries(raw: String): Series {
        if (raw.isBlank()) return Series(emptyList(), emptyList())

        return try {
            val json = JSONObject(raw)
            Series(
                historical = parseFloatArray(json.optJSONArray("historical")),
                forecast = parseFloatArray(json.optJSONArray("forecast"))
            )
        } catch (_: Exception) {
            Series(emptyList(), emptyList())
        }
    }

    private fun parseFloatArray(array: JSONArray?): List<Float?> {
        if (array == null) return emptyList()

        return (0 until array.length()).map { i ->
            if (array.isNull(i)) null else array.optDouble(i).toFloat()
        }
    }

    /** Catmull-Rom-style smoothing so the curve reads like Chart.js's tension:0.35. */
    private fun smoothPath(points: List<Offset>): Path {
        val path = Path()
        if (points.isEmpty()) return path

        path.moveTo(points[0].x, points[0].y)
        if (points.size == 1) return path

        val smoothing = 0.2f
        for (i in 0 until points.size - 1) {
            val p0 = points[if (i == 0) i else i - 1]
            val p1 = points[i]
            val p2 = points[i + 1]
            val p3 = points[if (i + 2 < points.size) i + 2 else i + 1]

            val c1 = Offset(p1.x + (p2.x - p0.x) * smoothing, p1.y + (p2.y - p0.y) * smoothing)
            val c2 = Offset(p2.x - (p3.x - p1.x) * smoothing, p2.y - (p3.y - p1.y) * smoothing)

            path.cubicTo(c1.x, c1.y, c2.x, c2.y, p2.x, p2.y)
        }

        return path
    }

    @Composable
    fun Render(node: NativeUINode, modifier: Modifier) {
        val p = node.props
        val series = parseSeries(p.getString("data", ""))
        val historicalColor = Color(p.getColor("historical_color", 0xFF2D5D5A.toInt()))
        val forecastColor = Color(p.getColor("forecast_color", 0xFFEC7C0E.toInt()))
        val selectedIndex = p.getInt("selected_index", -1)
        val onChangeCb = p.getCallbackId("on_change")
        val nodeId = node.id
        val count = maxOf(series.historical.size, series.forecast.size)

        Canvas(
            modifier = modifier.pointerInput(count) {
                if (count > 1 && onChangeCb != 0) {
                    detectTapGestures { offset ->
                        val step = size.width.toFloat() / (count - 1)
                        val index = (offset.x / step).roundToInt().coerceIn(0, count - 1)
                        NativeUIBridge.sendTabChangeEvent(onChangeCb, nodeId, index)
                    }
                }
            }
        ) {
            if (count < 2) return@Canvas

            fun valueAt(index: Int): Float? = series.historical.getOrNull(index) ?: series.forecast.getOrNull(index)

            val maxVal = (0 until count).mapNotNull { valueAt(it) }.maxOrNull()?.takeIf { it > 0f } ?: 1f
            val stepX = size.width / (count - 1)

            fun toOffset(index: Int, value: Float): Offset {
                val x = index * stepX
                val y = size.height - (value / maxVal) * size.height
                return Offset(x, y)
            }

            fun drawSeries(values: List<Float?>, color: Color, dashed: Boolean) {
                val segments = mutableListOf<List<Offset>>()
                var current = mutableListOf<Offset>()

                values.forEachIndexed { i, v ->
                    if (v != null) {
                        current.add(toOffset(i, v))
                    } else if (current.isNotEmpty()) {
                        segments.add(current)
                        current = mutableListOf()
                    }
                }
                if (current.isNotEmpty()) segments.add(current)

                segments.forEach { points ->
                    if (points.size < 2) return@forEach

                    val linePath = smoothPath(points)

                    val fillPath = Path().apply {
                        addPath(linePath)
                        lineTo(points.last().x, size.height)
                        lineTo(points.first().x, size.height)
                        close()
                    }
                    drawPath(fillPath, color = color.copy(alpha = 0.15f), style = Fill)

                    drawPath(
                        path = linePath,
                        color = color,
                        style = Stroke(
                            width = 5f,
                            cap = StrokeCap.Round,
                            join = StrokeJoin.Round,
                            pathEffect = if (dashed) PathEffect.dashPathEffect(floatArrayOf(18f, 12f)) else null
                        )
                    )
                }
            }

            drawSeries(series.historical, historicalColor, dashed = false)
            drawSeries(series.forecast, forecastColor, dashed = true)

            if (selectedIndex in 0 until count) {
                valueAt(selectedIndex)?.let { v ->
                    val point = toOffset(selectedIndex, v)
                    val markerColor = if (series.historical.getOrNull(selectedIndex) != null) historicalColor else forecastColor

                    drawLine(
                        color = markerColor.copy(alpha = 0.4f),
                        start = Offset(point.x, 0f),
                        end = Offset(point.x, size.height),
                        strokeWidth = 2f
                    )
                    drawCircle(color = markerColor, radius = 7f, center = point)
                    drawCircle(color = Color.White, radius = 3f, center = point)
                }
            }
        }
    }
}
