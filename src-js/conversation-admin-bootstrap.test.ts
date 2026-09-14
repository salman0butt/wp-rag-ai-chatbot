import { bootstrapAdminApp } from "./index";

type TestElement = {
  tagName: string;
  props: Record<string, unknown> | null;
  children: unknown[];
};

const createElement = (
  tagName: string,
  props: Record<string, unknown> | null,
  ...children: unknown[]
): TestElement => ({ tagName, props, children });

const createDeferred = <T>() => {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((resolvePromise) => {
    resolve = resolvePromise;
  });

  return { promise, resolve };
};

const response = (payload: unknown) => ({
  ok: true,
  status: 200,
  json: async () => payload,
});

describe("conversation admin bootstrap", () => {
  afterEach(() => {
    document.body.innerHTML = "";
    window.location.hash = "";
    Reflect.deleteProperty(window, "wpRagAiChatbotAdminConfig");
    Reflect.deleteProperty(window, "fetch");
  });

  it("loads the bounded conversation list for the current route page", async () => {
    const render = jest.fn();
    Object.defineProperty(window, "wp", {
      configurable: true,
      value: {
        element: {
          createElement,
          render,
        },
      },
    });

    const root = document.createElement("div");
    root.id = "wp-rag-ai-chatbot-admin";
    document.body.append(root);
    Object.defineProperty(window, "wpRagAiChatbotAdminConfig", {
      configurable: true,
      value: {
        plugin: "wp-rag-ai-chatbot",
        restBase: "https://example.test/wp-json/wp-rag-ai-chatbot/v1",
        nonce: "rest-nonce",
      },
    });

    const fetcher = jest.fn(async (requestUrl: string) =>
      response(
        requestUrl.includes("/admin/onboarding/readiness")
          ? { ready: true, next_step: "complete" }
          : {
              items: [],
              page: 2,
              per_page: 25,
            },
      ),
    );
    Object.defineProperty(window, "fetch", {
      configurable: true,
      value: fetcher,
    });

    expect(bootstrapAdminApp("#/conversations?page=2")).toBe(true);
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(fetcher).toHaveBeenCalledWith(
      "https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/conversations?page=2&per_page=25",
      expect.objectContaining({ method: "GET" }),
    );
    expect(render).toHaveBeenCalled();
  });

  it("loads the selected conversation detail from the current route", async () => {
    const render = jest.fn();
    Object.defineProperty(window, "wp", {
      configurable: true,
      value: {
        element: {
          createElement,
          render,
        },
      },
    });

    const root = document.createElement("div");
    root.id = "wp-rag-ai-chatbot-admin";
    document.body.append(root);
    Object.defineProperty(window, "wpRagAiChatbotAdminConfig", {
      configurable: true,
      value: {
        plugin: "wp-rag-ai-chatbot",
        restBase: "https://example.test/wp-json/wp-rag-ai-chatbot/v1",
        nonce: "rest-nonce",
      },
    });

    const fetcher = jest.fn(async (requestUrl: string) =>
      response(
        requestUrl.includes("/admin/onboarding/readiness")
          ? { ready: true, next_step: "complete" }
          : {
              conversation: {
                conversation_id: "conv-1",
                bot_id: "bot-1",
                started_at: "2026-09-14 10:00:00",
                messages: [],
              },
            },
      ),
    );
    Object.defineProperty(window, "fetch", {
      configurable: true,
      value: fetcher,
    });

    expect(bootstrapAdminApp("#/conversations/conv-1?page=2")).toBe(true);
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(fetcher).toHaveBeenCalledWith(
      "https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/conversations/conv-1?message_limit=100",
      expect.objectContaining({ method: "GET" }),
    );
    expect(render).toHaveBeenCalled();
  });

  it("keeps a newer conversation detail when an older request resolves last", async () => {
    const render = jest.fn();
    Object.defineProperty(window, "wp", {
      configurable: true,
      value: {
        element: {
          createElement,
          render,
        },
      },
    });

    const root = document.createElement("div");
    root.id = "wp-rag-ai-chatbot-admin";
    document.body.append(root);
    Object.defineProperty(window, "wpRagAiChatbotAdminConfig", {
      configurable: true,
      value: {
        plugin: "wp-rag-ai-chatbot",
        restBase: "https://example.test/wp-json/wp-rag-ai-chatbot/v1",
        nonce: "rest-nonce",
      },
    });

    const firstDetail = createDeferred<ReturnType<typeof response>>();
    const secondDetail = createDeferred<ReturnType<typeof response>>();
    const fetcher = jest.fn((requestUrl: string) => {
      if (requestUrl.includes("/admin/onboarding/readiness")) {
        return Promise.resolve(response({ ready: true, next_step: "complete" }));
      }
      if (requestUrl.includes("/admin/conversations/conv-1?")) {
        return firstDetail.promise;
      }
      if (requestUrl.includes("/admin/conversations/conv-2?")) {
        return secondDetail.promise;
      }

      return Promise.reject(new Error(`Unexpected request: ${requestUrl}`));
    });
    Object.defineProperty(window, "fetch", {
      configurable: true,
      value: fetcher,
    });

    expect(bootstrapAdminApp("#/conversations/conv-1")).toBe(true);
    await new Promise((resolve) => setTimeout(resolve, 0));

    window.location.hash = "#/conversations/conv-2";
    window.dispatchEvent(new HashChangeEvent("hashchange"));

    secondDetail.resolve(
      response({
        conversation: {
          conversation_id: "conv-2",
          bot_id: "bot-2",
          started_at: "2026-09-14 11:00:00",
          messages: [],
        },
      }),
    );
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(JSON.stringify(render.mock.calls.at(-1)?.[0])).toContain(
      '"data-conversation-id":"conv-2"',
    );

    firstDetail.resolve(
      response({
        conversation: {
          conversation_id: "conv-1",
          bot_id: "bot-1",
          started_at: "2026-09-14 10:00:00",
          messages: [],
        },
      }),
    );
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(JSON.stringify(render.mock.calls.at(-1)?.[0])).toContain(
      '"data-conversation-id":"conv-2"',
    );
  });
});