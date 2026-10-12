import { useEffect, useState } from "react";
import { api, useHub } from "./hub";
import { type Row } from "./data";

export default function CustomerPicker({
    branch,
    value,
    onChange,
}: {
    branch: number;
    value: number | string;
    onChange: (id: number) => void;
}) {
    const { run, toast } = useHub();
    const [term, setTerm] = useState(""),
        [items, setItems] = useState<Row[]>([]),
        [name, setName] = useState(""),
        [chosen, setChosen] = useState<Row | null>(null);
    useEffect(() => {
        setChosen(null);
        setTerm("");
        setItems([]);
    }, [branch]);
    useEffect(() => {
        let active = true;
        const timer = setTimeout(
            () =>
                api(
                    "manage/customers?" +
                        new URLSearchParams({
                            branch_id: String(branch),
                            search: term,
                            ...(value && !term
                                ? { user_id: String(value) }
                                : {}),
                        }),
                )
                    .then((r) => {
                        if (active) {
                            setItems(r.data);
                            if (value)
                                setChosen(
                                    r.data.find(
                                        (c: Row) => c.id === Number(value),
                                    ) ?? null,
                                );
                        }
                    })
                    .catch((e) => {
                        if (active) toast(e.message);
                    }),
            250,
        );
        return () => {
            active = false;
            clearTimeout(timer);
        };
    }, [term, branch, value]);
    return (
        <div className="customer-picker form-section rounded-xl border border-[#dce4dc] p-4">
            <label className="block text-sm font-bold">
                Khách hàng
                <input
                    className="input mt-2 w-full"
                    aria-label="Số điện thoại hoặc tên khách"
                    placeholder="Tìm theo số điện thoại hoặc tên"
                    value={term}
                    onChange={(e) => setTerm(e.target.value)}
                />
            </label>
            {chosen && (
                <p className="my-2 rounded-lg bg-green-50 p-3 text-sm">
                    ✓ {chosen.name} · {chosen.phone}
                </p>
            )}
            <div className="my-2 max-h-36 overflow-auto">
                {items
                    .filter((c) => c.id !== Number(value))
                    .map((c) => (
                        <button
                            type="button"
                            className="block w-full rounded-lg p-2 text-left text-sm hover:bg-green-50"
                            key={c.id}
                            onClick={() => {
                                onChange(c.id);
                                setChosen(c);
                            }}
                        >
                            {c.name} · {c.phone}
                        </button>
                    ))}
            </div>
            {items.length === 0 && /^0\d{9}$/.test(term.trim()) && (
                <div className="space-y-2">
                    <p className="text-sm text-slate-500">
                        Chưa có khách với số này. Thêm khách để đặt tại quầy.
                    </p>
                    <input
                        aria-label="Tên khách mới"
                        className="input w-full"
                        placeholder="Họ tên khách"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                    />
                    <button
                        type="button"
                        className="btn-outline"
                        disabled={!name.trim()}
                        onClick={() =>
                            run(async () => {
                                const r = await api(
                                    "manage/customers",
                                    "POST",
                                    {
                                        branch_id: branch,
                                        name: name.trim(),
                                        phone: term.trim(),
                                    },
                                );
                                onChange(r.data.id);
                                setChosen(r.data);
                                setItems([r.data]);
                                toast("Đã thêm khách hàng.");
                            })
                        }
                    >
                        Thêm khách
                    </button>
                </div>
            )}
        </div>
    );
}
