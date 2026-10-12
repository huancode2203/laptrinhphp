const paths = {
    eye: "M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0",
    eyeOff: "m3 3 18 18M9 5a11 11 0 0 1 3 0c6 0 10 7 10 7a20 20 0 0 1-4 4M6 6a20 20 0 0 0-4 6s4 7 10 7c2 0 4-.7 6-2M9 9a4 4 0 0 0 6 6",
    edit: "M16 3l5 5M3 21l5-1L21 7l-5-5L3 16v5",
    heart: "M12 21 3 12a5 5 0 0 1 9-7 5 5 0 0 1 9 7l-9 9",
    lock: "M5 10h14v11H5zM8 10V6a4 4 0 0 1 8 0v4M12 14v3",
    mail: "M3 5h18v14H3zM3 5l9 7 9-7",
    phone: "M5 3h4l2 5-3 2a16 16 0 0 0 6 6l2-3 5 2v4c0 2-3 3-5 2A22 22 0 0 1 3 8C2 6 3 3 5 3",
    arrow: "M5 12h14M12 5l7 7-7 7",
    northeast: "M6 18 18 6M6 6h12v12",
    search: "M21 21l-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0",
    court: "M4 3h16v18H4zM4 12h16M7 3v18M17 3v18M7 8h10M7 16h10M12 3v5M12 16v5",
    calendar:
        "M5 5h14a2 2 0 0 1 2 2v13H3V7a2 2 0 0 1 2-2M7 3v4M17 3v4M3 11h18M7 15h2M15 15h2",
    clock: "M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0M12 7v5l3 2",
    pin: "M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0M15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0",
    people: "M15 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0M5 21v-2a7 7 0 0 1 14 0v2M19 4a3 3 0 0 1 0 6M21 19v-2a5 5 0 0 0-3-4",
    trophy: "M7 3h10v7a5 5 0 0 1-10 0V3M7 5H3v3a4 4 0 0 0 4 4M17 5h4v3a4 4 0 0 1-4 4M12 15v6M8 21h8",
    racket: "M16 14a6 8 38 1 0-8-9 6 8 38 0 0 8 9M9 15l-6 6M5 17l2 2M10 5l7 7M8 8l6 6M13 4l-5 7M17 6l-7 7",
    spark: "m12 3 2.3 6.7L21 12l-6.7 2.3L12 21l-2.3-6.7L3 12l6.7-2.3L12 3",
    check: "m5 12 4 4L19 6",
    star: "m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3 6.4 20.2l1.1-6.2L3 9.6l6.2-.9L12 3",
    gift: "M3 8h18v4H3zM5 12v9h14v-9M12 8v13M12 8H7a3 3 0 1 1 3-3l2 3M12 8h5a3 3 0 1 0-3-3l-2 3",
    menu: "M4 6h16M4 12h16M4 18h16",
    close: "m6 6 12 12M6 18 18 6",
    chevron: "m8 10 4 4 4-4",
    user: "M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0M4 21v-2a8 8 0 0 1 16 0v2",
    chat: "M21 11a9 9 0 0 1-9 9H3l2-5a9 9 0 1 1 16-4M8 10h8M8 14h5",
    grid: "M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z",
    bag: "M4 7h16l1 14H3L4 7M8 7V5a4 4 0 0 1 8 0v2",
    chart: "M4 3v18h17M8 16v-4M13 16V8M18 16V5",
    settings:
        "M12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M5 19l2-2M17 7l2-2",
    shield: "m12 2 8 3v6c0 5-8 11-8 11S4 16 4 11V5l8-3M8 12l3 3 5-6",
    help: "M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0M9 8a3 3 0 0 1 6 0c0 2-3 2-3 5M12 16v1",
    logout: "M9 3H4v18h5M9 12h12M17 8l4 4-4 4",
} as const;
export type IconName = keyof typeof paths;
export default function Icon({
    name,
    size = 20,
    className = "",
}: {
    name: IconName;
    size?: number;
    className?: string;
}) {
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.7"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={className}
            aria-hidden="true"
        >
            <path d={paths[name]} />
        </svg>
    );
}
