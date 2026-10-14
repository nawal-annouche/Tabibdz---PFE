(function () {
  const STYLE_ID = "tdz-shared-toast-styles";
  const STACK_ID = "tdz-toast-stack";

  function ensureStyles() {
    if (document.getElementById(STYLE_ID)) return;
    const style = document.createElement("style");
    style.id = STYLE_ID;
    style.textContent = `
      .tdz-toast-stack {
        position: fixed;
        right: 20px;
        bottom: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 10px;
        pointer-events: none;
      }
      .tdz-toast {
        max-width: min(420px, calc(100vw - 32px));
        padding: 12px 16px;
        border-radius: 14px;
        background: #102116;
        color: #fff;
        font-size: 13.5px;
        font-weight: 600;
        line-height: 1.45;
        box-shadow: 0 14px 34px rgba(16, 33, 22, 0.22);
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.22s ease, transform 0.22s ease;
      }
      .tdz-toast.show {
        opacity: 1;
        transform: translateY(0);
      }
      .tdz-toast--success {
        background: #15803d;
      }
      .tdz-toast--error {
        background: #991b1b;
      }
      .tdz-toast--info {
        background: #1d4ed8;
      }
      @media (max-width: 640px) {
        .tdz-toast-stack {
          left: 16px;
          right: 16px;
          bottom: 16px;
          align-items: stretch;
        }
        .tdz-toast {
          max-width: none;
        }
      }
    `;
    document.head.appendChild(style);
  }

  function ensureStack() {
    let stack = document.getElementById(STACK_ID);
    if (!stack) {
      stack = document.createElement("div");
      stack.id = STACK_ID;
      stack.className = "tdz-toast-stack";
      document.body.appendChild(stack);
    }
    return stack;
  }

  function showToast(message, type = "info", options = {}) {
    ensureStyles();
    const stack = ensureStack();
    const toast = document.createElement("div");
    const variant = ["success", "error", "info"].includes(type) ? type : "info";
    const duration = Number(options.duration) > 0 ? Number(options.duration) : 3200;

    toast.className = `tdz-toast tdz-toast--${variant}`;
    toast.textContent = String(message || "");
    stack.appendChild(toast);

    requestAnimationFrame(() => {
      toast.classList.add("show");
    });

    window.setTimeout(() => {
      toast.classList.remove("show");
      window.setTimeout(() => toast.remove(), 240);
    }, duration);
  }

  window.showToast = showToast;
  window.TabibToast = { show: showToast };
})();
