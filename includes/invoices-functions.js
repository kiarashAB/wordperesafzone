// includes/invoices-functions.js
(function (w) {
  console.log("🔥 invoices-functions.js FROM:", document.currentScript && document.currentScript.src);

  function withinView(sel) { return document.querySelector("#mbp-view " + sel); }

  function toast(msg, type = "ok") {
    const t = document.getElementById("mbp-toast");
    if (!t) return alert(msg);
    t.textContent = msg;
    t.style.background = (type === "error") ? "rgba(239,68,68,.85)" : "rgba(0,0,0,.85)";
    t.style.borderColor = (type === "error") ? "rgba(239,68,68,.5)" : "rgba(255,255,255,.12)";
    t.classList.add("show");
    setTimeout(() => t.classList.remove("show"), 2800);
  }

  // =======================
  // Template picker (default for print)
  // =======================
  const MBP_TPL_KEY = "mbp_invoice_tpl_default";

  function getSelectedTpl() {
    const sel = document.querySelector("#mbp-view #mbp-inv-template");
    if (sel && sel.value) return sel.value;
    return localStorage.getItem(MBP_TPL_KEY) || "classic_a";
  }

  function initTemplatePicker() {
    const sel = document.querySelector("#mbp-view #mbp-inv-template");
    if (!sel) return;

    const saved = localStorage.getItem(MBP_TPL_KEY);
    sel.value = saved || sel.dataset.default || "classic_a";

    if (sel.dataset.bound === "1") return;
    sel.dataset.bound = "1";

    sel.addEventListener("change", () => {
      localStorage.setItem(MBP_TPL_KEY, sel.value);
    });
  }

  // =======================
  // Ajax helper
  // =======================
  async function mbpAjax(action, payload = {}) {
    const fd = new FormData();
    fd.append("action", action);
    fd.append("nonce", w.MBP_ADMIN_NONCE || "");
    Object.keys(payload).forEach(k => fd.append(k, payload[k] ?? ""));

    const res = await fetch(w.MBP_AJAX_URL, { method: "POST", body: fd });
    const text = await res.text();

    let json;
    try { json = JSON.parse(text); }
    catch (e) {
      console.error("❌ AJAX NOT JSON:", { action, status: res.status, text });
      throw new Error("پاسخ سرور JSON نیست. Console را ببین.");
    }
    return json;
  }

  // =======================
  // Woo Orders tab
  // =======================
  async function loadWooOrders() {
    const box = withinView("#mbp-wc-orders");
    if (!box) return;

    box.innerHTML = `
      <div style="text-align:center;color:#cbd5e1;padding:30px;">
        <div class="mbp-loading" style="width:36px;height:36px;margin:0 auto 12px;"></div>
        در حال بارگذاری سفارش‌ها...
      </div>
    `;

    try {
      const r = await mbpAjax("mbp_get_wc_orders", {});
      if (!r.success) {
        box.innerHTML = `<div style="padding:14px;border-radius:12px;border:1px solid rgba(239,68,68,.35);background:rgba(239,68,68,.10);color:#fecaca;">
          ${r.data?.message || "خطا در دریافت سفارش‌ها"}
        </div>`;
        return;
      }
      box.innerHTML = r.data.html;
    } catch (err) {
      console.error(err);
      box.innerHTML = `<div style="padding:14px;border-radius:12px;border:1px solid rgba(239,68,68,.35);background:rgba(239,68,68,.10);color:#fecaca;">
        ${err.message || "خطا"}
      </div>`;
    }
  }

  function bindWooRefresh() {
    const b = withinView("#mbp-wc-refresh");
    if (!b) return;
    if (b.dataset.bound === "1") return;
    b.dataset.bound = "1";

    b.addEventListener("click", (e) => {
      e.preventDefault();
      loadWooOrders();
    });
  }

  // کلیک ساخت فاکتور از سفارش ووکامرس (delegate)
  document.addEventListener("click", async (e) => {
    const btn = e.target.closest(".mbp-wc-make-inv");
    if (!btn) return;

    e.preventDefault();
    const order_id = btn.dataset.order;
    if (!order_id) return;

    btn.disabled = true;
    const old = btn.textContent;
    btn.textContent = "در حال ساخت...";

    try {
      const r = await mbpAjax("mbp_create_invoice_from_wc_order", { order_id });
      if (!r.success) {
        toast(r.data?.message || "خطا در ساخت فاکتور", "error");
        return;
      }

      toast("✅ فاکتور ساخته شد");
      await loadWooOrders();

      if (r.data?.invoice_id) {
        const url = w.MBP_AJAX_URL
          + "?action=mbp_print_invoice"
          + "&invoice_id=" + encodeURIComponent(r.data.invoice_id)
          + "&tpl=" + encodeURIComponent(getSelectedTpl())
          + "&nonce=" + encodeURIComponent(w.MBP_ADMIN_NONCE);

        window.open(url, "_blank", "noopener,noreferrer");
      }
    } catch (err) {
      console.error(err);
      toast(err.message || "خطا", "error");
    } finally {
      btn.disabled = false;
      btn.textContent = old;
    }
  });

  // =======================
  // Invoices list
  // =======================
  async function loadInvoices() {
    const box = withinView("#mbp-invoices-list");
    if (!box) return;

    box.innerHTML = `
      <div style="text-align:center;color:#cbd5e1;padding:30px;">
        <div class="mbp-loading" style="width:36px;height:36px;margin:0 auto 12px;"></div>
        در حال بارگذاری فاکتورها...
      </div>
    `;

    try {
      const r = await mbpAjax("mbp_get_invoices", {});
      if (!r.success) {
        box.innerHTML = `<div style="padding:14px;border-radius:12px;border:1px solid rgba(239,68,68,.35);background:rgba(239,68,68,.10);color:#fecaca;">
          ${r.data?.message || "خطا در دریافت فاکتورها"}
        </div>`;
        return;
      }
      box.innerHTML = r.data.html;
    } catch (err) {
      console.error(err);
      box.innerHTML = `<div style="padding:14px;border-radius:12px;border:1px solid rgba(239,68,68,.35);background:rgba(239,68,68,.10);color:#fecaca;">
        ${err.message || "خطا"}
      </div>`;
    }
  }

  function bindRefresh() {
    const btn = withinView("#mbp-inv-refresh");
    if (!btn) return;
    if (btn.dataset.bound === "1") return;
    btn.dataset.bound = "1";

    btn.addEventListener("click", (e) => {
      e.preventDefault();
      loadInvoices();
    });
  }

  // =======================
  // Create invoice UI (multi-items)
  // =======================
  function invNumber(n) {
    n = Number(n || 0);
    if (Number.isNaN(n)) n = 0;
    return n;
  }

  function formatToman(n) {
    n = invNumber(n);
    return n.toLocaleString('en-US') + ' تومان';
  }

  function itemRowHTML(data = {}) {
    const desc = data.description || '';
    const qty = data.qty ?? 1;
    const price = data.unit_price ?? 0;

    return `
      <div class="mbp-inv-item-row"
           style="display:grid;grid-template-columns:1.3fr .45fr .6fr .7fr .25fr;gap:8px;align-items:center;">
        <input class="mbp-form-input inv-item-desc" placeholder="اسم آیتم" value="${desc}">
        <input class="mbp-form-input inv-item-qty" type="number" min="1" value="${qty}">
        <input class="mbp-form-input inv-item-price" type="number" min="0" value="${price}">
        <div class="mbp-inv-item-total" style="font-weight:800;opacity:.9;">0 تومان</div>
        <button type="button" class="mbp-btn mbp-inv-remove-item" style="padding:6px 10px;border-color:rgba(239,68,68,.35);">
          ✖
        </button>
      </div>
    `;
  }

  function readItems() {
    const rows = Array.from(document.querySelectorAll('#mbp-view .mbp-inv-item-row'));
    const items = [];

    rows.forEach(r => {
      const description = (r.querySelector('.inv-item-desc')?.value || '').trim();
      const qty = invNumber(r.querySelector('.inv-item-qty')?.value || 1) || 1;
      const unit_price = invNumber(r.querySelector('.inv-item-price')?.value || 0);

      if (description) items.push({ description, qty, unit_price });
    });

    return items;
  }

  function recalcInvoiceTotals() {
    const rows = Array.from(document.querySelectorAll('#mbp-view .mbp-inv-item-row'));
    let subtotal = 0;

    rows.forEach(r => {
      const qty = invNumber(r.querySelector('.inv-item-qty')?.value || 1) || 1;
      const price = invNumber(r.querySelector('.inv-item-price')?.value || 0);
      const line = Math.max(0, qty * price);
      subtotal += line;

      const totalBox = r.querySelector('.mbp-inv-item-total');
      if (totalBox) totalBox.textContent = formatToman(line);
    });

    const discount = invNumber(document.querySelector('#mbp-view #inv_discount')?.value || 0);
    const tax = invNumber(document.querySelector('#mbp-view #inv_tax')?.value || 0);

    const total = Math.max(0, subtotal - discount + tax);

    const totalBox = document.querySelector('#mbp-view #inv_total_box');
    if (totalBox) totalBox.textContent = formatToman(total);

    return { subtotal, discount, tax, total };
  }

  function initCreateInvoiceUI() {
    const container = document.querySelector('#mbp-view #mbp-inv-items');
    const addBtn = document.querySelector('#mbp-view #mbp-inv-add-item');
    if (!container || !addBtn) return;

    // فقط یکبار bind
    if (container.dataset.bound !== "1") {
      container.dataset.bound = "1";

      container.addEventListener('click', (e) => {
        const rm = e.target.closest('.mbp-inv-remove-item');
        if (!rm) return;
        rm.closest('.mbp-inv-item-row')?.remove();
        recalcInvoiceTotals();
      });

      container.addEventListener('input', (e) => {
        if (
          e.target.classList.contains('inv-item-qty') ||
          e.target.classList.contains('inv-item-price') ||
          e.target.classList.contains('inv-item-desc')
        ) {
          recalcInvoiceTotals();
        }
      });

      ['#inv_discount', '#inv_tax'].forEach(sel => {
        const el = document.querySelector('#mbp-view ' + sel);
        if (el && el.dataset.bound !== "1") {
          el.dataset.bound = "1";
          el.addEventListener('input', recalcInvoiceTotals);
        }
      });
    }

    // اگر خالیه، یک ردیف بساز
    if (!container.querySelector('.mbp-inv-item-row')) {
      container.insertAdjacentHTML('beforeend', itemRowHTML({ qty: 1, unit_price: 0 }));
    }

    if (addBtn.dataset.bound !== "1") {
      addBtn.dataset.bound = "1";
      addBtn.onclick = () => {
        container.insertAdjacentHTML('beforeend', itemRowHTML({ qty: 1, unit_price: 0 }));
        recalcInvoiceTotals();
        const sc = container.closest('.mbp-inv-items-scroll') || container;
        sc.scrollTo({ top: sc.scrollHeight, behavior: "smooth" });
      };
    }

    recalcInvoiceTotals();
  }

  function bindCreateInvoice() {
    const btn = withinView("#mbp-create-invoice-btn");
    if (!btn) return;
    if (btn.dataset.bound === "1") return;
    btn.dataset.bound = "1";

    btn.addEventListener("click", async (e) => {
      e.preventDefault();

      const customer_name = withinView("#inv_customer_name")?.value?.trim() || "";
      const mobile = withinView("#inv_mobile")?.value?.trim() || "";
      const email = withinView("#inv_email")?.value?.trim() || "";
      const notes = withinView("#inv_notes")?.value?.trim() || "";

      const discount = invNumber(withinView("#inv_discount")?.value || 0);
      const tax = invNumber(withinView("#inv_tax")?.value || 0);

      const items = readItems();
      if (!customer_name) return toast("نام مشتری الزامی است", "error");
      if (!items.length) return toast("حداقل یک آیتم با شرح وارد کنید", "error");

      const totals = recalcInvoiceTotals();

      btn.disabled = true;
      const old = btn.textContent;
      btn.textContent = "در حال ذخیره...";

      try {
        const r = await mbpAjax("mbp_create_invoice", {
          customer_name, mobile, email, notes,
          discount, tax,
          total: totals.total,
          items: JSON.stringify(items),
        });

        if (!r.success) {
          toast(r.data?.message || "ناموفق", "error");
          return;
        }

        toast("✅ فاکتور ساخته شد");

        // برو به لیست و رفرش کن
        withinView('[data-tab="list"]')?.click();
        loadInvoices();
      } catch (err) {
        console.error(err);
        toast(err.message || "خطا", "error");
      } finally {
        btn.disabled = false;
        btn.textContent = old;
      }
    });
  }

  // =======================
  // Settings tab (invoice seller info)
  // =======================
  async function loadSettings() {
    const hint = withinView("#inv-settings-hint");
    if (hint) hint.textContent = "در حال بارگذاری...";

    try {
      const r = await mbpAjax("mbp_get_invoice_settings", {});
      if (!r.success) {
        if (hint) hint.textContent = "خطا در بارگذاری";
        toast(r.data?.message || "خطا", "error");
        return;
      }

      const s = r.data.settings || {};
      const map = {
        seller_logo_url: 'inv_set_seller_logo_url',
        seller_name: 'inv_set_seller_name',
        seller_phone: 'inv_set_seller_phone',
        seller_email: 'inv_set_seller_email',
        seller_website: 'inv_set_seller_website',
        seller_postcode: 'inv_set_seller_postcode',
        seller_reg_number: 'inv_set_seller_reg_number',
        seller_economic_code: 'inv_set_seller_economic_code',
        seller_address1: 'inv_set_seller_address1',
        seller_address2: 'inv_set_seller_address2',
        seller_city: 'inv_set_seller_city',
        seller_country: 'inv_set_seller_country',
        seller_custom_label: 'inv_set_seller_custom_label',
        seller_custom_value: 'inv_set_seller_custom_value',
        order_meta_label: 'inv_set_order_meta_label',
        order_meta_key: 'inv_set_order_meta_key',
        customer_meta_label: 'inv_set_customer_meta_label',
        customer_meta_key: 'inv_set_customer_meta_key',
      };

      Object.keys(map).forEach(k => {
        const el = withinView("#" + map[k]);
        if (el) el.value = s[k] ?? "";
      });

      if (hint) hint.textContent = "✅ بارگذاری شد";
    } catch (err) {
      console.error(err);
      if (hint) hint.textContent = "خطا";
      toast(err.message || "خطا", "error");
    }
  }

  async function saveSettings() {
    const hint = withinView("#inv-settings-hint");
    if (hint) hint.textContent = "در حال ذخیره...";

    const map = {
      seller_logo_url: 'inv_set_seller_logo_url',
      seller_name: 'inv_set_seller_name',
      seller_phone: 'inv_set_seller_phone',
      seller_email: 'inv_set_seller_email',
      seller_website: 'inv_set_seller_website',
      seller_postcode: 'inv_set_seller_postcode',
      seller_reg_number: 'inv_set_seller_reg_number',
      seller_economic_code: 'inv_set_seller_economic_code',
      seller_address1: 'inv_set_seller_address1',
      seller_address2: 'inv_set_seller_address2',
      seller_city: 'inv_set_seller_city',
      seller_country: 'inv_set_seller_country',
      seller_custom_label: 'inv_set_seller_custom_label',
      seller_custom_value: 'inv_set_seller_custom_value',
      order_meta_label: 'inv_set_order_meta_label',
      order_meta_key: 'inv_set_order_meta_key',
      customer_meta_label: 'inv_set_customer_meta_label',
      customer_meta_key: 'inv_set_customer_meta_key',
    };

    const payload = {};
    Object.keys(map).forEach(k => {
      const el = withinView("#" + map[k]);
      payload[k] = el ? el.value : "";
    });

    try {
      const r = await mbpAjax("mbp_save_invoice_settings", payload);
      if (!r.success) {
        if (hint) hint.textContent = "خطا در ذخیره";
        toast(r.data?.message || "خطا", "error");
        return;
      }
      if (hint) hint.textContent = "✅ ذخیره شد";
      toast("✅ تنظیمات فاکتور ذخیره شد");
    } catch (err) {
      console.error(err);
      if (hint) hint.textContent = "خطا";
      toast(err.message || "خطا", "error");
    }
  }

  function bindSettingsButtons() {
    // save btn
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("#mbp-view #mbp-save-invoice-settings");
      if (!btn) return;
      e.preventDefault();
      saveSettings();
    });
  }

  // =======================
  // Tabs
  // =======================
  function bindTabs() {
    const tabs = Array.from(document.querySelectorAll("#mbp-view .mbp-inv-tab"));
    const panes = Array.from(document.querySelectorAll("#mbp-view .mbp-inv-pane"));

    function show(name) {
      tabs.forEach(t => t.classList.toggle("active", t.dataset.tab === name));
      panes.forEach(p => p.style.display = (p.dataset.pane === name ? "block" : "none"));

      if (name === "list") loadInvoices();
      if (name === "create") initCreateInvoiceUI();
      if (name === "woo") { bindWooRefresh(); loadWooOrders(); }
      if (name === "settings") loadSettings();

      // چون ممکنه select تو pane های مختلف رندر بشه
      initTemplatePicker();
    }

    tabs.forEach(t => {
      if (t.dataset.bound === "1") return;
      t.dataset.bound = "1";
      t.addEventListener("click", (e) => {
        e.preventDefault();
        show(t.dataset.tab);
      });
    });

    show("list");
  }

  // =======================
  // Delete invoice (delegate)
  // =======================
  document.addEventListener("click", async (e) => {
    const delBtn = e.target.closest(".mbp-inv-del");
    if (!delBtn) return;

    e.preventDefault();
    const id = delBtn.dataset.id;
    if (!id) return;

    if (!confirm("⚠️ حذف فاکتور؟ این عمل قابل بازگشت نیست.")) return;

    try {
      const r = await mbpAjax("mbp_delete_invoice", { id });
      if (!r.success) {
        toast(r.data?.message || "خطا در حذف", "error");
        return;
      }
      toast("🗑️ حذف شد");
      loadInvoices();
    } catch (err) {
      console.error(err);
      toast(err.message || "خطا", "error");
    }
  });

  // =======================
  // Print invoice (delegate)
  // =======================
  document.addEventListener("click", function (e) {
    const btn = e.target.closest(".mbp-inv-print");
    if (!btn) return;

    e.preventDefault();

    const id = btn.dataset.id;
    const tpl = getSelectedTpl();

    const url =
      w.MBP_AJAX_URL +
      "?action=mbp_print_invoice" +
      "&invoice_id=" + encodeURIComponent(id) +
      "&tpl=" + encodeURIComponent(tpl) +
      "&nonce=" + encodeURIComponent(w.MBP_ADMIN_NONCE);

    window.open(url, "_blank", "noopener,noreferrer");
  });

  // =======================
  // Public init
  // =======================
  w.MBP_Invoices = {
    init() {
      console.log("✅ MBP_Invoices.init");
      bindTabs();
      bindRefresh();
      bindCreateInvoice();
      bindSettingsButtons();
      initTemplatePicker();
    }
  };

  console.log("✅ MBP_Invoices READY:", w.MBP_Invoices);
})(window);
