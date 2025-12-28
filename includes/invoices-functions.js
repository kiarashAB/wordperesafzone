(function () {
  function q(sel) { return document.querySelector(sel); }
  function qa(sel) { return Array.from(document.querySelectorAll(sel)); }

  function toNum(v) {
    v = String(v ?? '').replace(/[^\d.]/g, '');
    return Number(v || 0);
  }

  function money(n) {
    n = Math.round(toNum(n));
    return n.toLocaleString('fa-IR');
  }

  function calcRow(row) {
    const qty = toNum(row.querySelector('.mbp-inv-qty').value);
    const price = toNum(row.querySelector('.mbp-inv-price').value);
    const sum = qty * price;
    row.querySelector('.mbp-inv-sum').textContent = money(sum);
    return sum;
  }

  function recalc() {
    const tbody = q('#mbp-view #mbp-inv-items');
    if (!tbody) return;

    let subtotal = 0;
    tbody.querySelectorAll('tr').forEach(tr => subtotal += calcRow(tr));

    const discount = toNum(q('#mbp-view #mbp-inv-discount')?.value);
    const tax = toNum(q('#mbp-view #mbp-inv-tax')?.value);

    const total = Math.max(0, subtotal + tax - discount);

    const stEl = q('#mbp-view #mbp-inv-subtotal');
    const tEl = q('#mbp-view #mbp-inv-total');
    if (stEl) stEl.textContent = money(subtotal);
    if (tEl) tEl.textContent = money(total);
  }

  function addItemRow(data) {
    const tbody = q('#mbp-view #mbp-inv-items');
    if (!tbody) return;

    const title = data?.title || '';
    const qty = data?.qty ?? 1;
    const price = data?.price ?? 0;

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td style="padding:10px;border-top:1px solid rgba(255,255,255,.08);">
        <input class="mbp-form-input mbp-inv-title" type="text" placeholder="مثلا هزینه خدمات" value="${escapeHtml(title)}">
      </td>
      <td style="padding:10px;border-top:1px solid rgba(255,255,255,.08);">
        <input class="mbp-form-input mbp-inv-qty" type="number" min="1" value="${qty}">
      </td>
      <td style="padding:10px;border-top:1px solid rgba(255,255,255,.08);">
        <input class="mbp-form-input mbp-inv-price" type="number" min="0" value="${price}">
      </td>
      <td style="padding:10px;border-top:1px solid rgba(255,255,255,.08);font-weight:900;">
        <span class="mbp-inv-sum">0</span>
      </td>
      <td style="padding:10px;border-top:1px solid rgba(255,255,255,.08);">
        <button class="mbp-btn mbp-inv-remove">حذف</button>
      </td>
    `;
    tbody.appendChild(tr);

    tr.querySelector('.mbp-inv-qty').addEventListener('input', recalc);
    tr.querySelector('.mbp-inv-price').addEventListener('input', recalc);
    tr.querySelector('.mbp-inv-title').addEventListener('input', recalc);
    tr.querySelector('.mbp-inv-remove').addEventListener('click', (e) => {
      e.preventDefault();
      tr.remove();
      recalc();
    });

    recalc();
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[m]));
  }

  async function post(action, extra) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('nonce', window.MBP_ADMIN_NONCE);
    Object.keys(extra || {}).forEach(k => fd.append(k, extra[k]));
    const res = await fetch(window.MBP_AJAX_URL, { method: 'POST', body: fd });
    return await res.json();
  }

  // این باید global باشه چون از اسکریپت اصلی صدا زده میشه
  window.loadInvoices = async function () {
    const list = q('#mbp-view #mbp-invoices-list') || q('#mbp-invoices-list');
    if (!list) return;

    try {
      const data = await post('mbp_get_invoices', {});
      if (data?.success) {
        list.innerHTML = data.data.html;
      } else {
        list.innerHTML = `<div style="color:#ef4444;font-weight:900;">${escapeHtml(data?.data?.message || 'خطا')}</div>`;
      }
    } catch (e) {
      console.error(e);
      list.innerHTML = `<div style="color:#ef4444;font-weight:900;">خطای شبکه</div>`;
    }
  };

  window.initInvoiceBuilder = function () {
    // آیتم اولیه
    const tbody = q('#mbp-view #mbp-inv-items');
    if (!tbody || tbody.children.length === 0) addItemRow();

    q('#mbp-view #mbp-inv-add-item')?.addEventListener('click', (e) => {
      e.preventDefault();
      addItemRow();
    });

    q('#mbp-view #mbp-inv-discount')?.addEventListener('input', recalc);
    q('#mbp-view #mbp-inv-tax')?.addEventListener('input', recalc);

    q('#mbp-view #mbp-inv-save')?.addEventListener('click', async (e) => {
      e.preventDefault();

      const customer = (q('#mbp-view #mbp-inv-customer')?.value || '').trim();
      if (!customer) {
        alert('نام مشتری را وارد کن');
        return;
      }

      const items = [];
      (q('#mbp-view #mbp-inv-items')?.querySelectorAll('tr') || []).forEach(tr => {
        const title = (tr.querySelector('.mbp-inv-title')?.value || '').trim();
        const qty = toNum(tr.querySelector('.mbp-inv-qty')?.value);
        const price = toNum(tr.querySelector('.mbp-inv-price')?.value);
        if (!title) return;
        if (qty <= 0) return;
        items.push({ title, qty, price });
      });

      if (!items.length) {
        alert('حداقل یک آیتم معتبر وارد کن');
        return;
      }

      const payload = {
        customer_name: customer,
        customer_phone: (q('#mbp-view #mbp-inv-phone')?.value || '').trim(),
        customer_email: (q('#mbp-view #mbp-inv-email')?.value || '').trim(),
        discount: String(toNum(q('#mbp-view #mbp-inv-discount')?.value)),
        tax: String(toNum(q('#mbp-view #mbp-inv-tax')?.value)),
        note: (q('#mbp-view #mbp-inv-note')?.value || '').trim(),
        items_json: JSON.stringify(items)
      };

      const btn = q('#mbp-view #mbp-inv-save');
      const old = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'در حال ذخیره...';

      try {
        const res = await post('mbp_invoice_create', payload);
        if (!res?.success) {
          alert(res?.data?.message || 'ذخیره ناموفق');
          return;
        }

        // ریست
        q('#mbp-view #mbp-inv-customer').value = '';
        q('#mbp-view #mbp-inv-phone').value = '';
        q('#mbp-view #mbp-inv-email').value = '';
        q('#mbp-view #mbp-inv-discount').value = 0;
        q('#mbp-view #mbp-inv-tax').value = 0;
        q('#mbp-view #mbp-inv-note').value = '';
        q('#mbp-view #mbp-inv-items').innerHTML = '';
        addItemRow();

        await window.loadInvoices();
        alert('✅ فاکتور ذخیره شد');
      } finally {
        btn.disabled = false;
        btn.textContent = old;
      }
    });

    // delete / print
    document.addEventListener('click', async (e) => {
      const delBtn = e.target.closest('.mbp-invoice-del');
      const printBtn = e.target.closest('.mbp-invoice-print');

      if (delBtn) {
        e.preventDefault();
        const id = delBtn.dataset.id;
        if (!id) return;
        if (!confirm('حذف شود؟')) return;

        const res = await post('mbp_invoice_delete', { id });
        if (!res?.success) {
          alert(res?.data?.message || 'خطا');
          return;
        }
        await window.loadInvoices();
      }

      if (printBtn) {
        e.preventDefault();
        const id = printBtn.dataset.id;
        if (!id) return;

        const res = await post('mbp_invoice_print', { id });
        if (!res?.success) {
          alert(res?.data?.message || 'خطا');
          return;
        }

        const w = window.open('', '_blank');
        w.document.open();
        w.document.write(res.data.html);
        w.document.close();
        w.focus();
        w.print();
      }
    });

    recalc();
  };
})();
