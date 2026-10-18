import { useState } from "react";
import { api, useHub } from "./hub";
import { statusText } from "./data";
import { Card, Editor } from "./ui";
import GoogleButton from "./GoogleButton";
import Icon from "./Icon";
export default function WorkspaceAccount() {
    const { data, refresh, toast } = useHub();
    const user = data!.user;
    const [edit, setEdit] = useState("");
    return (
        <div className="mx-auto max-w-4xl space-y-5">
            <section className="relative overflow-hidden rounded-3xl bg-[#173e31] p-6 text-white sm:p-8">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -right-8 -top-8 rotate-12 text-[#c5f566]/10"
                >
                    <Icon name="racket" size={240} />
                </div>
                <div className="relative flex flex-wrap items-center gap-5">
                    <span
                        className="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-[#c5f566] text-2xl font-bold text-[#173e31]"
                        aria-hidden="true"
                    >
                        {user.name
                            .trim()
                            .split(/\s+/)
                            .slice(-2)
                            .map((part: string) => part[0])
                            .join("")}
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="text-xs font-semibold tracking-[0.16em] text-[#c5f566]">
                            TÀI KHOẢN LÀM VIỆC
                        </p>
                        <h2 className="mt-2 break-words font-display text-3xl font-bold">
                            {user.name}
                        </h2>
                        <p className="mt-2 text-sm text-white/75">
                            {statusText(user.role)} · Badminton Hub
                        </p>
                    </div>
                </div>
            </section>
            <div className="grid items-start gap-5 lg:grid-cols-2">
                <Card>
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-xl font-bold">Thông tin liên hệ</h2>
                        <Icon name="user" className="text-emerald-700" />
                    </div>
                    <dl className="mt-5 space-y-5">
                        <div>
                            <dt className="flex items-center gap-2 text-sm text-slate-500">
                                <Icon name="mail" size={16} />
                                Email
                            </dt>
                            <dd className="mt-1 break-all font-semibold">
                                {user.email}
                            </dd>
                        </div>
                        <div>
                            <dt className="flex items-center gap-2 text-sm text-slate-500">
                                <Icon name="phone" size={16} />
                                Số điện thoại
                            </dt>
                            <dd className="mt-1 font-semibold">
                                {user.phone || "Chưa bổ sung"}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-slate-500">Vai trò</dt>
                            <dd className="mt-1 font-semibold">
                                {statusText(user.role)}
                            </dd>
                        </div>
                    </dl>
                    <button
                        className="btn mt-6"
                        onClick={() => setEdit("profile")}
                    >
                        <Icon name="edit" size={17} />
                        Cập nhật thông tin
                    </button>
                </Card>
                <Card>
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-xl font-bold">
                            Đăng nhập & bảo mật
                        </h2>
                        <Icon name="shield" className="text-emerald-700" />
                    </div>
                    <p className="mt-4 text-sm leading-6 text-slate-500">
                        Liên kết Google để đăng nhập vào đúng tài khoản làm
                        việc. Vai trò và quyền theo chi nhánh được giữ theo phân
                        công.
                    </p>
                    {user.google_connected ? (
                        <p className="mt-4 font-semibold text-emerald-700">
                            Đã liên kết Google
                        </p>
                    ) : (
                        <div className="mt-4 max-w-xs">
                            <GoogleButton
                                linked
                                after={async () => {
                                    await refresh();
                                }}
                            />
                        </div>
                    )}
                    <div className="mt-6 border-t border-slate-100 pt-5">
                        <p className="mb-3 text-sm leading-6 text-slate-500">
                            Dùng mật khẩu riêng cho tài khoản làm việc và giữ
                            thông tin đăng nhập trên thiết bị của bạn.
                        </p>
                        <button
                            className="btn-outline"
                            onClick={() => setEdit("password")}
                        >
                            <Icon name="lock" size={17} />
                            Đổi mật khẩu
                        </button>
                    </div>
                </Card>
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
        </div>
    );
}
