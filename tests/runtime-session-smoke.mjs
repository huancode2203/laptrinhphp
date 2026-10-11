// Run against a local Laragon instance containing the documented demo accounts.
// Unlike in-process PHP tests, this exercises concurrent Apache/PHP requests.
const base = process.env.HUB_TEST_URL || "http://127.0.0.1:8088";
if (!["127.0.0.1", "localhost", "[::1]"].includes(new URL(base).hostname)) {
    throw new Error("This check only accepts a local demo instance.");
}
let checks = 0;
for (const role of ["customer", "manager", "staff", "cashier", "admin"]) {
    const cookies = new Map();
    async function request(path, method = "GET", body, token = "") {
        const response = await fetch(base + "/api/v1/" + path, {
            method,
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                Cookie: [...cookies].map(([k, v]) => k + "=" + v).join("; "),
                "X-CSRF-TOKEN": token,
            },
            body: body ? JSON.stringify(body) : undefined,
            signal: AbortSignal.timeout(30000),
        });
        for (const header of response.headers.getSetCookie()) {
            const first = header.split(";")[0],
                index = first.indexOf("=");
            cookies.set(first.slice(0, index), first.slice(index + 1));
        }
        if (!response.ok)
            throw new Error(role + " " + path + ": HTTP " + response.status);
        checks++;
        return response.json();
    }
    const csrf = (await request("csrf")).csrf_token;
    await request(
        "login",
        "POST",
        { email: role + "@badminton.test", password: "Demo@12345" },
        csrf,
    );
    const hub = (await request("hub")).data;
    if (hub.user?.role !== role) throw new Error("Unexpected signed-in role");
    const branch =
        hub.user.branch_id ?? hub.managed_branch_ids[0] ?? hub.branches[0].id;
    const paths = [
        "hub",
        "me",
        "csrf",
        "account",
        "bookings",
        "notifications",
        "holds/active",
        "community/posts",
    ];
    if (role !== "customer")
        paths.push(
            "manage/hub?branch_id=" + branch,
            "manage/features?branch_id=" + branch,
        );
    if (role === "admin") paths.push("admin/hub");
    for (let round = 0; round < 3; round++) {
        const results = await Promise.all(paths.map((path) => request(path)));
        if (
            results[0].data.user?.role !== role ||
            results[1].data?.role !== role
        )
            throw new Error("Session lost during concurrent requests: " + role);
    }
    await request("logout", "POST", {}, (await request("csrf")).csrf_token);
    console.log(role + ": concurrent reads and session persistence passed");
}
console.log(JSON.stringify({ checks, result: "passed" }));
