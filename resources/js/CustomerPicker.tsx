import { useEffect, useState } from "react";
import { api, useHub } from "./hub";
import { type Row } from "./data";
import { Editor } from "./ui";

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
        [adding, setAdding] = useState(false),
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
                    placeholder="Tìm theo số điện thoại, email hoặc tên"
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
            <button
                type="button"
                className="btn-outline mt-3"
                onClick={() => setAdding(true)}
            >
                + Thêm khách hàng mới
            </button>
            {adding && (
                <Editor
                    title="Thêm khách đặt tại quầy"
                    description="Kiểm tra số điện thoại và email để tránh tạo trùng tài khoản khách."
                    initial={{
                        name: "",
                        phone: /^0\d{9}$/.test(term.trim()) ? term.trim() : "",
                        email: "",
                        skill_level: "Sơ cấp",
                    }}
                    fields={[
                        {
                            name: "name",
                            label: "Họ và tên",
                            required: true,
                            maxLength: 100,
                        },
                        {
                            name: "phone",
                            label: "Số điện thoại",
                            type: "tel",
                            required: true,
                            maxLength: 15,
                        },
                        {
                            name: "email",
                            label: "Email (nếu có)",
                            type: "email",
                            maxLength: 255,
                        },
                        {
                            name: "skill_level",
                            label: "Trình độ",
                            required: true,
                            options: [
                                "Mới chơi",
                                "Sơ cấp",
                                "Trung cấp",
                                "Nâng cao",
                            ].map((value) => ({ value, label: value })),
                        },
                    ]}
                    close={() => setAdding(false)}
                    save={async (values) => {
                        const r = await api("manage/customers", "POST", {
                            ...values,
                            email: values.email || null,
                            branch_id: branch,
                        });
                        onChange(r.data.id);
                        setChosen(r.data);
                        setItems([r.data]);
                        setAdding(false);
                        toast("Đã thêm khách hàng.");
                    }}
                />
            )}
        </div>
    );
}
