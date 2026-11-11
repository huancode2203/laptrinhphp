import { useEffect, useState } from "react";
import { api, downloadFile, useHub } from "./hub";
import { stamp, type Row } from "./data";
import { Card, Editor, Table, cell, Empty, type Field } from "./ui";
export default function BackupPanel() {
    const { toast } = useHub();
    const [tab, setTab] = useState("backups"),
        [page, setPage] = useState(1),
        [version, setVersion] = useState(0),
        [result, setResult] = useState<Row | null>(null),
        [error, setError] = useState(""),
        [edit, setEdit] = useState<Row | null>(null),
        [preview, setPreview] = useState<Row | null>(null);
    useEffect(() => {
        let live = true;
        setResult(null);
        setError("");
        api("admin/" + tab + "?page=" + page)
            .then((r) => {
                if (live) setResult(r.data);
            })
            .catch((e) => {
                if (live) setError(e.message);
            });
        return () => {
            live = false;
        };
    }, [tab, page, version]);
    const fields: Field[] = [
        {
            name: "password",
            label: "Mật khẩu quản trị hiện tại",
            type: "password",
            required: true,
        },
    ];
    if (edit?.action === "restore")
        fields.push({
            name: "confirmation",
            label: "Nhập KHOI PHUC CSDL để xác nhận thay thế dữ liệu",
            required: true,
        });
    return (
        <div className="space-y-5">
            <div className="flex gap-2">
                {[
                    ["backups", "Sao lưu CSDL"],
                    ["retention", "Tệp lưu tạm"],
                ].map(([id, label]) => (
                    <button
                        key={id}
                        className={tab === id ? "btn" : "btn-outline"}
                        onClick={() => {
                            setTab(id);
                            setPage(1);
                        }}
                    >
                        {label}
                    </button>
                ))}
            </div>
            <Card>
                <h2 className="text-xl font-bold">
                    {tab === "backups"
                        ? "Sao lưu và khôi phục CSDL"
                        : "Tệp không còn sử dụng"}
                </h2>
                <p className="my-3 text-sm text-slate-600">
                    {tab === "backups"
                        ? "Bản sao lưu được mã hóa bằng khóa máy chủ, chứa CSDL và đường dẫn tệp. Cần sao lưu riêng thư mục ảnh và khóa máy chủ để chuyển sang máy khác. Khôi phục thay thế dữ liệu và kết thúc các phiên đăng nhập; hệ thống tạo bản dự phòng trước khi thực hiện."
                        : "Chỉ chuyển vào vùng lưu tạm các tệp đã tải lên hơn 14 ngày và không có bài viết, bình luận, tin nhắn, đánh giá, ảnh đại diện hay giao dịch tham chiếu. Có thể khôi phục trước khi dọn vĩnh viễn; chỉ dọn sau 30 ngày."}
                </p>
                <button
                    className="btn"
                    onClick={() =>
                        setEdit({
                            action: tab === "backups" ? "create" : "archive",
                        })
                    }
                >
                    {tab === "backups"
                        ? "+ Tạo bản sao lưu"
                        : "Tìm và chuyển tệp không dùng vào lưu tạm"}
                </button>
                {error && (
                    <p className="field-error mt-4" role="alert">
                        {error}
                    </p>
                )}
                {!result && !error && <Empty text="Đang tải danh sách…" />}
                {result && (
                    <>
                        <Table
                            headers={["Bản lưu", "Thông tin", "Thao tác"]}
                            pageSize={null}
                        >
                            {result.data.map((r: Row) => (
                                <tr key={r.reference ?? r.id}>
                                    <td className={cell}>
                                        <b>{r.reference ?? r.name}</b>
                                        <p>
                                            {stamp(
                                                r.created_at ?? r.archived_at,
                                            )}
                                        </p>
                                    </td>
                                    <td className={cell}>
                                        {((r.bytes ?? r.size) / 1024).toFixed(
                                            1,
                                        )}{" "}
                                        KB
                                        <p>
                                            {tab === "backups"
                                                ? (
                                                      {
                                                          manual: "Tạo thủ công",
                                                          before_restore:
                                                              "Dự phòng trước khôi phục",
                                                          scheduled:
                                                              "Sao lưu tự động",
                                                      } as Row
                                                  )[r.purpose]
                                                : r.purged_at
                                                  ? "Đã dọn vĩnh viễn"
                                                  : r.restored_at
                                                    ? "Đã khôi phục"
                                                    : "Lưu tạm đến " +
                                                      stamp(r.purge_after)}
                                        </p>
                                    </td>
                                    <td className={cell}>
                                        <div className="flex flex-wrap gap-2">
                                            {tab === "backups" ? (
                                                <>
                                                    {[
                                                        "download",
                                                        "inspect",
                                                    ].map((action) => (
                                                        <button
                                                            key={action}
                                                            className="btn-outline"
                                                            onClick={() =>
                                                                setEdit({
                                                                    action,
                                                                    reference:
                                                                        r.reference,
                                                                })
                                                            }
                                                        >
                                                            {action ===
                                                            "download"
                                                                ? "Tải bản mã hóa"
                                                                : "Kiểm tra / khôi phục"}
                                                        </button>
                                                    ))}
                                                </>
                                            ) : (
                                                !r.restored_at &&
                                                !r.purged_at && (
                                                    <>
                                                        <button
                                                            className="btn-outline"
                                                            onClick={() =>
                                                                setEdit({
                                                                    action: "restore-media",
                                                                    id: r.id,
                                                                })
                                                            }
                                                        >
                                                            Khôi phục
                                                        </button>
                                                        {Date.parse(
                                                            r.purge_after,
                                                        ) <= Date.now() && (
                                                            <button
                                                                className="btn-outline text-red-700"
                                                                onClick={() =>
                                                                    setEdit({
                                                                        action: "purge",
                                                                        id: r.id,
                                                                    })
                                                                }
                                                            >
                                                                Dọn vĩnh viễn
                                                            </button>
                                                        )}
                                                    </>
                                                )
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </Table>
                        {!result.data.length && (
                            <Empty text="Chưa có bản lưu." />
                        )}
                        {result.last_page > 1 && (
                            <div className="mt-4 flex items-center gap-3">
                                <button
                                    className="btn-outline"
                                    disabled={page <= 1}
                                    onClick={() => setPage(page - 1)}
                                >
                                    Trước
                                </button>
                                Trang {page}/{result.last_page}
                                <button
                                    className="btn-outline"
                                    disabled={page >= result.last_page}
                                    onClick={() => setPage(page + 1)}
                                >
                                    Tiếp
                                </button>
                            </div>
                        )}
                    </>
                )}
            </Card>
            {preview && (
                <Card>
                    <h3 className="font-bold">
                        Bản sao lưu hợp lệ · {stamp(preview.created_at)}
                    </h3>
                    <p className="my-3">
                        {Object.keys(preview.tables).length} bảng ·{" "}
                        {Object.values(preview.tables).reduce(
                            (sum: number, count) => sum + Number(count),
                            0,
                        )}{" "}
                        dòng. Bật bảo trì bằng lệnh{" "}
                        <code>php artisan down</code> trên máy chủ trước khi
                        khôi phục. Sau khi khôi phục xong chạy{" "}
                        <code>php artisan up</code>.
                    </p>
                    <button
                        className="btn-outline text-red-700"
                        onClick={() =>
                            setEdit({
                                action: "restore",
                                reference: preview.reference,
                            })
                        }
                    >
                        Khôi phục CSDL từ bản này
                    </button>
                </Card>
            )}
            {edit && (
                <Editor
                    title={
                        (
                            {
                                create: "Tạo bản sao lưu CSDL",
                                download: "Tải bản sao lưu đã mã hóa",
                                inspect: "Kiểm tra bản sao lưu",
                                restore: "Khôi phục CSDL",
                                archive: "Lưu tạm tệp không dùng",
                                "restore-media": "Khôi phục tệp",
                                purge: "Dọn vĩnh viễn tệp hết hạn",
                            } as Row
                        )[edit.action]
                    }
                    fields={fields}
                    close={() => setEdit(null)}
                    save={async (d) => {
                        if (edit.action === "download")
                            await downloadFile(
                                "admin/backups/" + edit.reference + "/download",
                                { password: d.password },
                                "badminton-" + edit.reference + ".hubbackup",
                            );
                        else if (edit.action === "inspect") {
                            const response = await api(
                                "admin/backups/" + edit.reference + "/inspect",
                                "POST",
                                { password: d.password },
                            );
                            setPreview({
                                ...response.data,
                                reference: edit.reference,
                            });
                        } else if (["create", "restore"].includes(edit.action))
                            await api(
                                "admin/backups" +
                                    (edit.action === "create"
                                        ? ""
                                        : "/" + edit.reference + "/restore"),
                                "POST",
                                {
                                    password: d.password,
                                    ...(edit.action === "restore"
                                        ? { confirmation: d.confirmation }
                                        : {}),
                                },
                            );
                        else
                            await api(
                                "admin/retention/" +
                                    (edit.action === "restore-media"
                                        ? "restore"
                                        : edit.action),
                                "POST",
                                {
                                    password: d.password,
                                    ...(edit.id ? { id: edit.id } : {}),
                                },
                            );
                        setEdit(null);
                        setVersion((v) => v + 1);
                        toast("Đã hoàn tất thao tác.");
                    }}
                />
            )}
        </div>
    );
}
