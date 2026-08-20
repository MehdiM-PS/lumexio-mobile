import SwiftUI

/// Draws the two null-padded series ("historical" solid + filled, "forecast"
/// dashed + filled) as smooth curves, mirroring the web app's Chart.js
/// config (tension 0.35, ~15%/12% fill opacity) with hand-rolled SwiftUI
/// Canvas drawing — no charting library dependency needed for one chart.
struct LineChartRenderer: View {
    let node: NativeUINode

    private struct SeriesData {
        let historical: [Float?]
        let forecast: [Float?]
    }

    private func parseSeries(_ raw: String) -> SeriesData {
        guard let data = raw.data(using: .utf8),
              let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any] else {
            return SeriesData(historical: [], forecast: [])
        }

        func floats(_ key: String) -> [Float?] {
            guard let array = json[key] as? [Any?] else { return [] }
            return array.map { value -> Float? in
                guard let value, !(value is NSNull), let number = value as? NSNumber else { return nil }
                return number.floatValue
            }
        }

        return SeriesData(historical: floats("historical"), forecast: floats("forecast"))
    }

    /// Catmull-Rom-style smoothing so the curve reads like Chart.js's tension:0.35.
    private func smoothPath(_ points: [CGPoint]) -> Path {
        var path = Path()
        guard let first = points.first else { return path }
        path.move(to: first)
        guard points.count > 1 else { return path }

        let smoothing: CGFloat = 0.2
        for i in 0..<(points.count - 1) {
            let p0 = points[i == 0 ? i : i - 1]
            let p1 = points[i]
            let p2 = points[i + 1]
            let p3 = points[i + 2 < points.count ? i + 2 : i + 1]

            let c1 = CGPoint(x: p1.x + (p2.x - p0.x) * smoothing, y: p1.y + (p2.y - p0.y) * smoothing)
            let c2 = CGPoint(x: p2.x - (p3.x - p1.x) * smoothing, y: p2.y - (p3.y - p1.y) * smoothing)

            path.addCurve(to: p2, control1: c1, control2: c2)
        }

        return path
    }

    var body: some View {
        let p = node.props
        let series = parseSeries(p.getString("data", default: ""))
        let historicalColor = Color(argb: p.getColor("historical_color", default: 0xFF2D5D5A))
        let forecastColor = Color(argb: p.getColor("forecast_color", default: 0xFFEC7C0E))
        let selectedIndex = p.getInt("selected_index", default: -1)
        let onChangeCb = p.getCallbackId("on_change")
        let nodeId = node.id
        let count = max(series.historical.count, series.forecast.count)

        // A `let`-bound closure, not a nested `func` — a local function
        // declaration inside `body` fails to compile ("closure containing a
        // declaration cannot be used with result builder 'ViewBuilder'"),
        // since `@ViewBuilder` bodies only tolerate `let`/`var` statements.
        let value: (Int) -> Float? = { index in
            (index < series.historical.count ? series.historical[index] : nil)
                ?? (index < series.forecast.count ? series.forecast[index] : nil)
        }

        GeometryReader { geo in
            Canvas { context, size in
                guard count > 1 else { return }

                let combined = (0..<count).compactMap { value($0) }
                let maxVal = max(combined.max() ?? 1, 1)
                let stepX = size.width / CGFloat(count - 1)

                func point(_ index: Int, _ value: Float) -> CGPoint {
                    let x = CGFloat(index) * stepX
                    let y = size.height - (CGFloat(value) / CGFloat(maxVal)) * size.height
                    return CGPoint(x: x, y: y)
                }

                func draw(_ values: [Float?], color: Color, dashed: Bool) {
                    var segments: [[CGPoint]] = []
                    var current: [CGPoint] = []

                    for (i, v) in values.enumerated() {
                        if let v {
                            current.append(point(i, v))
                        } else if !current.isEmpty {
                            segments.append(current)
                            current = []
                        }
                    }
                    if !current.isEmpty { segments.append(current) }

                    for points in segments {
                        guard points.count > 1, let last = points.last, let first = points.first else { continue }

                        let linePath = smoothPath(points)

                        var fillPath = linePath
                        fillPath.addLine(to: CGPoint(x: last.x, y: size.height))
                        fillPath.addLine(to: CGPoint(x: first.x, y: size.height))
                        fillPath.closeSubpath()

                        context.fill(fillPath, with: .color(color.opacity(0.15)))

                        var strokeStyle = StrokeStyle(lineWidth: 2.5, lineCap: .round, lineJoin: .round)
                        if dashed { strokeStyle.dash = [6, 4] }
                        context.stroke(linePath, with: .color(color), style: strokeStyle)
                    }
                }

                draw(series.historical, color: historicalColor, dashed: false)
                draw(series.forecast, color: forecastColor, dashed: true)

                if selectedIndex >= 0, selectedIndex < count, let v = value(selectedIndex) {
                    let markerPoint = point(selectedIndex, v)
                    let markerColor = selectedIndex < series.historical.count && series.historical[selectedIndex] != nil
                        ? historicalColor
                        : forecastColor

                    var linePath = Path()
                    linePath.move(to: CGPoint(x: markerPoint.x, y: 0))
                    linePath.addLine(to: CGPoint(x: markerPoint.x, y: size.height))
                    context.stroke(linePath, with: .color(markerColor.opacity(0.4)), lineWidth: 1)

                    context.fill(Path(ellipseIn: CGRect(x: markerPoint.x - 4, y: markerPoint.y - 4, width: 8, height: 8)), with: .color(markerColor))
                    context.fill(Path(ellipseIn: CGRect(x: markerPoint.x - 1.5, y: markerPoint.y - 1.5, width: 3, height: 3)), with: .color(.white))
                }
            }
            .contentShape(Rectangle())
            .gesture(
                DragGesture(minimumDistance: 0)
                    .onEnded { drag in
                        guard count > 1, onChangeCb != 0 else { return }
                        let step = geo.size.width / CGFloat(count - 1)
                        let index = Int((drag.location.x / step).rounded())
                        let clamped = min(max(index, 0), count - 1)
                        NativeUIBridge.sendTabChangeEvent(onChangeCb, nodeId: nodeId, index: clamped)
                    }
            )
        }
    }
}
