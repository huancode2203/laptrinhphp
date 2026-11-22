import { useRef, useState } from "react";
import { api, key } from "./hub";
import type { Row } from "./data";

export function useChatDelivery(
    user: Row,
    conversationId: number | string | null,
    confirmed: (messages: Row[], id: number | string) => void,
) {
    const [outbox, setOutbox] = useState<Row[]>([]);
    const current = useRef(conversationId);
    const receive = useRef(confirmed);
    const inFlight = useRef(new Set<string>());
    current.current = conversationId;
    receive.current = confirmed;

    async function deliver(draft: Row) {
        if (inFlight.current.has(draft.request_key)) return;
        inFlight.current.add(draft.request_key);
        setOutbox((rows) =>
            rows.map((row) =>
                row.id === draft.id
                    ? { ...row, delivery: "sending", error: "" }
                    : row,
            ),
        );
        try {
            const response = await api(
                draft.conversation_id === "hub-ai"
                    ? "ai/chat"
                    : "conversations/" + draft.conversation_id + "/messages",
                "POST",
                draft.conversation_id === "hub-ai"
                    ? { request_key: draft.request_key, message: draft.content }
                    : {
                          request_key: draft.request_key,
                          content: draft.content,
                          attachments: draft.attachments,
                      },
            );
            if (current.current === draft.conversation_id)
                receive.current(
                    Array.isArray(response.data)
                        ? response.data
                        : [response.data],
                    draft.conversation_id,
                );
            setOutbox((rows) => rows.filter((row) => row.id !== draft.id));
        } catch (failure) {
            setOutbox((rows) =>
                rows.map((row) =>
                    row.id === draft.id
                        ? {
                              ...row,
                              delivery: "failed",
                              error: (failure as Error).message,
                          }
                        : row,
                ),
            );
        } finally {
            inFlight.current.delete(draft.request_key);
        }
    }

    function send(content: string, attachments: number[]) {
        if (conversationId == null || (!content.trim() && !attachments.length))
            return;
        const requestKey = key();
        const draft = {
            id: "outbox-" + requestKey,
            request_key: requestKey,
            conversation_id: conversationId,
            user_id: user.id,
            user,
            content: content.trim(),
            attachments: [...attachments],
            created_at: new Date().toISOString(),
            delivery: "sending",
            deleted: true,
        };
        setOutbox((rows) => [...rows, draft]);
        void deliver(draft);
    }

    return {
        outbox: outbox.filter((row) => row.conversation_id === conversationId),
        sending: outbox.some(
            (row) =>
                row.conversation_id === conversationId &&
                row.delivery === "sending",
        ),
        send,
        retry: (draft: Row) => void deliver(draft),
    };
}
