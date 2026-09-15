type ElementFactory = (
  tagName: string,
  props: Record<string, unknown> | null,
  ...children: Array<unknown>
) => unknown;

export interface ConversationSummary {
  conversation_id: string;
  bot_id: string | null;
  started_at: string;
  latest_message_at: string | null;
  message_count: number;
}

export interface ConversationInboxScreenProps {
  items: ConversationSummary[];
  page: number;
  perPage: number;
  hasNextPage: boolean;
}

const messageCountLabel = (count: number): string =>
  `${count} ${count === 1 ? "message" : "messages"}`;

const filterField = (
  createElement: ElementFactory,
  id: string,
  name: string,
  label: string,
): unknown =>
  createElement(
    "div",
    null,
    createElement("label", { htmlFor: id }, label),
    createElement("input", { id, name, type: "text" }),
  );

export const ConversationInboxScreen = (
  props: ConversationInboxScreenProps,
): unknown => {
  const createElement = window.wp.element.createElement as ElementFactory;
  const page = Math.max(1, Math.trunc(props.page) || 1);
  const rows = props.items.map((conversation) =>
    createElement(
      "li",
      {
        key: conversation.conversation_id,
        "data-conversation-id": conversation.conversation_id,
      },
      createElement(
        "strong",
        null,
        conversation.bot_id ?? "Unassigned",
      ),
      " ",
      messageCountLabel(conversation.message_count),
    ),
  );

  return createElement(
    "section",
    { "data-conversation-inbox": true },
    createElement("h2", null, "Conversations"),
    createElement(
      "form",
      { "aria-label": "Conversation filters" },
      filterField(
        createElement,
        "conversation-search",
        "search",
        "Search conversations",
      ),
      filterField(createElement, "conversation-bot", "bot_id", "Bot ID"),
      filterField(
        createElement,
        "conversation-date-from",
        "date_from",
        "From",
      ),
      filterField(
        createElement,
        "conversation-date-to",
        "date_to",
        "To",
      ),
    ),
    props.items.length === 0
      ? createElement(
          "p",
          { "data-conversation-list-empty": true },
          "No conversations found.",
        )
      : createElement(
          "ul",
          { "data-per-page": props.perPage },
          ...rows,
        ),
    createElement(
      "nav",
      { "aria-label": "Conversation list pagination" },
      createElement(
        "button",
        {
          type: "button",
          "data-page": "previous",
          disabled: page <= 1,
        },
        "Previous",
      ),
      createElement("span", null, `Page ${page}`),
      createElement(
        "button",
        {
          type: "button",
          "data-page": "next",
          disabled: !props.hasNextPage,
        },
        "Next",
      ),
    ),
  );
};
