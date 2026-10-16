import { useState } from "react";
import { api, useHub } from "./hub";
import { statusText } from "./data";
import { Card, Editor } from "./ui";
export default function WorkspaceAccount() {
    const { data, refresh, toast } = useHub();
    const user = data!.user;
    const [edit, setEdit] = useState("");
    return (
        <Card className="mx-auto max-w-3xl">
            <p className="eyebrow">TÀI KHOẢN LÀM VIỆC</p>
            <h1 className="my-4 text-3xl font-bold">{user.name}</h1>
            <p>
                {statusText(user.role)} · {user.email}
            </p>
            <p className="my-3">{user.phone || "Chưa có số điện thoại"}</p>
            <div className="mt-6 flex flex-wrap gap-3">
                <button className="btn" onClick={() => setEdit("profile")}>
                    Cập nhật thông tin
                </button>
                <button
                    className="btn-outline"
                    onClick={() => setEdit("password")}
                >
                    Đổi mật khẩu
                </button>
            </div>
            {edit && (
                <Editor
                    title={
                        edit === "profile"
                            ? "Thông tin nhân sự"
                            : "Đổi mật khẩu"
                    }
                    close={() => setEdit("")}
                    initial={edit === "profile" ? user : {}}
                    fields={
                        edit === "profile"
                            ? [
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
                                      maxLength: 30,
                                  },
                              ]
                            : [
                                  {
                                      name: "current_password",
                                      label: "Mật khẩu hiện tại",
                                      type: "password",
                                      required: true,
                                  },
                                  {
                                      name: "password",
                                      label: "Mật khẩu mới",
                                      type: "password",
                                      required: true,
                                      maxLength: 72,
                                  },
                                  {
                                      name: "password_confirmation",
                                      label: "Nhập lại mật khẩu mới",
                                      type: "password",
                                      required: true,
                                      maxLength: 72,
                                  },
                              ]
                    }
                    save={async (d) => {
                        await api(
                            edit === "profile" ? "me" : "me/password",
                            edit === "profile" ? "PATCH" : "PUT",
                            edit === "profile"
                                ? {
                                      name: d.name,
                                      email: user.email,
                                      phone: d.phone,
                                  }
                                : d,
                        );
                        await refresh();
                        toast("Đã cập nhật tài khoản làm việc.");
                    }}
                />
            )}
        </Card>
    );
}
