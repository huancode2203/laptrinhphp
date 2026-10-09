import { useEffect, useState } from "react";
import {
    BarChart,
    Bar,
    CartesianGrid,
    XAxis,
    YAxis,
    Tooltip,
    ResponsiveContainer,
} from "recharts";
import { api, useHub } from "./hub";
import { day, money, type Row } from "./data";
import { Card, Table, cell } from "./ui";
const td = (value: React.ReactNode) => <td className={cell}>{value}</td>;

export default function ReportsView({ branch }: { branch: number | null }) {
    const { toast } = useHub();
    const [from, setFrom] = useState(day(-30)),
        [to, setTo] = useState(day()),
        [report, setReport] = useState<Row | null>(null);
    const query = new URLSearchParams({
        ...(branch ? { branch_id: String(branch) } : {}),
        from,
        to,
    }).toString();
    useEffect(() => {
        api("manage/reports?" + query)
            .then((r) => setReport(r.data))
            .catch((e) => toast(e.message));
    }, [query]);
    return (
        <section>
            <div className="mb-6 flex flex-wrap items-end gap-3">
                <label className="text-sm">
                    Từ ngày
                    <input
                        className="input mt-1 block"
                        type="date"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                    />
                </label>
                <label className="text-sm">
                    Đến ngày
                    <input
                        className="input mt-1 block"
                        type="date"
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                    />
                </label>
                {["xlsx", "pdf"].map((f) => (
                    <a
                        key={f}
                        className="btn-outline"
                        href={"/api/v1/manage/reports." + f + "?" + query}
                    >
                        Tải {f.toUpperCase()}
                    </a>
                ))}
            </div>
            {report && (
                <>
                    <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {[
                            ["Doanh thu ròng", money(report.net_revenue)],
                            ["Đã hoàn", money(report.refunded)],
                            ["Chưa thu", money(report.outstanding)],
                            ["Tỷ lệ hủy", report.cancellation_rate + "%"],
                        ].map(([label, value]) => (
                            <Card key={label}>
                                <p className="text-sm text-slate-500">
                                    {label}
                                </p>
                                <strong className="mt-3 block text-2xl">
                                    {value}
                                </strong>
                            </Card>
                        ))}
                    </div>
                    <Card>
                        <h2 className="mb-5 text-xl font-bold">
                            Doanh thu theo ngày thu tiền
                        </h2>
                        <div className="h-64 min-w-0">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={report.daily}>
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        vertical={false}
                                    />
                                    <XAxis
                                        dataKey="date"
                                        tickFormatter={(v) => v.slice(5)}
                                    />
                                    <YAxis
                                        tickFormatter={(v) => v / 1000 + "k"}
                                    />
                                    <Tooltip
                                        formatter={(v) => money(Number(v))}
                                    />
                                    <Bar
                                        dataKey="revenue"
                                        name="Doanh thu ròng"
                                        fill="#168449"
                                        radius={[5, 5, 0, 0]}
                                    />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </Card>
                    <div className="my-6 grid gap-5 lg:grid-cols-2">
                        {[
                            ["by_court", "Theo sân"],
                            ["by_product", "Theo sản phẩm"],
                            ["by_service", "Theo dịch vụ"],
                            ["by_branch", "Theo chi nhánh"],
                        ].map(([id, label]) => (
                            <Card key={id}>
                                <h2 className="mb-4 text-xl font-bold">
                                    {label}
                                </h2>
                                <Table headers={["Tên", "Doanh thu"]}>
                                    {report[id].map((r: Row) => (
                                        <tr key={r.name}>
                                            {td(r.name)}
                                            {td(money(r.revenue))}
                                        </tr>
                                    ))}
                                </Table>
                            </Card>
                        ))}
                    </div>
                    <Card>
                        <h2 className="mb-4 text-xl font-bold">
                            Công suất sân
                        </h2>
                        <p className="mb-4 text-sm text-slate-500">
                            Thời gian đã đặt / tổng giờ mở cửa trong khoảng ngày
                            chọn. Chỉ tính lịch còn hiệu lực.
                        </p>
                        <Table
                            headers={[
                                "Sân",
                                "Phút đã đặt",
                                "Phút có thể phục vụ",
                                "Công suất",
                            ]}
                        >
                            {report.occupancy.map((r: Row) => (
                                <tr key={r.name}>
                                    {td(r.name)}
                                    {td(r.minutes)}
                                    {td(r.available_minutes)}
                                    {td(r.percent + "%")}
                                </tr>
                            ))}
                        </Table>
                    </Card>
                    <Card>
                        <h2 className="mb-4 text-xl font-bold">
                            Giờ có nhiều lượt đặt
                        </h2>
                        <div className="overflow-x-auto">
                            <div className="grid min-w-[680px] grid-cols-[50px_repeat(24,1fr)] gap-1 text-center text-xs">
                                <span />
                                {Array.from({ length: 24 }, (_, i) => (
                                    <span key={i}>{i}h</span>
                                ))}
                                {[
                                    "CN",
                                    "T2",
                                    "T3",
                                    "T4",
                                    "T5",
                                    "T6",
                                    "T7",
                                ].flatMap((label, d) => [
                                    <b key={"label" + d}>{label}</b>,
                                    ...Array.from({ length: 24 }, (_, h) => {
                                        const n =
                                            report.heatmap.find(
                                                (r: Row) =>
                                                    r.weekday === d &&
                                                    r.hour === h,
                                            )?.slots ?? 0;
                                        return (
                                            <div
                                                key={d + "-" + h}
                                                className="rounded p-2"
                                                style={{
                                                    background: n
                                                        ? "rgba(22,132,73," +
                                                          Math.min(
                                                              0.15 + n * 0.08,
                                                              1,
                                                          ) +
                                                          ")"
                                                        : "#f1f5f3",
                                                    color:
                                                        n > 6
                                                            ? "white"
                                                            : "#0d1f2d",
                                                }}
                                                title={
                                                    label +
                                                    " " +
                                                    h +
                                                    "h: " +
                                                    n +
                                                    " lượt nửa giờ"
                                                }
                                            >
                                                {n || "·"}
                                            </div>
                                        );
                                    }),
                                ])}
                            </div>
                        </div>
                    </Card>
                </>
            )}
        </section>
    );
}
