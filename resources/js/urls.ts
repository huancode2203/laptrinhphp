export function appPath(path: string) {
    const base =
        document
            .querySelector<HTMLMetaElement>('meta[name="app-base"]')
            ?.content.replace(/\/$/, "") ?? "";
    if (/^(https?:|blob:|data:|#)/i.test(path)) return path;
    if (base && (path === base || path.startsWith(base + "/"))) return path;
    return base + "/" + path.replace(/^\//, "");
}
export function localLinks(value: any): any {
    if (
        typeof value === "string" &&
        /^\/(api\/v1|storage|images)\//.test(value)
    )
        return appPath(value);
    if (Array.isArray(value)) return value.map(localLinks);
    if (value && typeof value === "object")
        return Object.fromEntries(
            Object.entries(value).map(([key, item]) => [key, localLinks(item)]),
        );
    return value;
}
