import { money, type Row } from "./data";
import { formatDate } from "./dates";

export default function AiChatCriteria({
    result,
    canReply,
    sending,
    reply,
}: {
    result?: Row;
    canReply: boolean;
    sending: boolean;
    reply: (text: string) => void;
}) {
    if (!result) return null;
    const filters = result.filters ?? {};
    const area = filters.ward || filters.district;
    const amenityLabels: Record<string, string> = {
        Parking: "Bãi xe",
        WiFi: "Wi-Fi",
        Shower: "Phòng tắm",
        Cafe: "Cà phê",
    };
    const dateLabel = formatDate;
    const criteria = [
        filters.date && dateLabel(filters.date),
        filters.start_time &&
            filters.start_time +
                (filters.end_time ? "–" + filters.end_time : ""),
        area?.startsWith("__")
            ? "Khu vực chưa được hỗ trợ"
            : area || "Tất cả khu vực",
        filters.max_budget && "Tối đa " + money(filters.max_budget),
        ...(filters.amenities ?? []).map(
            (name: string) => amenityLabels[name] ?? name,
        ),
    ].filter(Boolean);
    const choices = [
        ...(result.area_options ?? []).map((area: string) => ({
            label: area,
            text: "Ở " + area,
        })),
        ...(result.area_options?.length
            ? [{ label: "Tất cả khu vực", text: "Tìm ở tất cả khu vực" }]
            : []),
        ...(filters.date_options ?? []).map((date: string) => ({
            label: dateLabel(date),
            text: "Ngày " + date,
        })),
    ];
    return (
        <>
            {(result.checked_at || result.area_options?.length > 0) && (
                <p className="mt-3 rounded-lg border border-green-100 bg-green-50 px-3 py-2 text-xs leading-6 text-green-900">
                    <b>Điều kiện tìm sân:</b> {criteria.join(" · ")}
                </p>
            )}
            {canReply && choices.length > 0 && (
                <div
                    className="mt-3 flex flex-wrap gap-2"
                    aria-label="Gợi ý trả lời HUB AI"
                >
                    {choices.map((choice) => (
                        <button
                            key={choice.text}
                            type="button"
                            className="btn-outline text-xs"
                            disabled={sending}
                            onClick={() => reply(choice.text)}
                        >
                            {choice.label}
                        </button>
                    ))}
                </div>
            )}
        </>
    );
}
