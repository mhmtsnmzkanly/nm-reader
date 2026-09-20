import { test } from "node:test";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";
import { createModalService } from "../modules/modal.js";

function createTestDom() {
  return new JSDOM(`<!DOCTYPE html><html><body>
    <div class="modal fade" id="panel-confirm-modal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <i id="panel-confirm-modal-icon"></i>
              <span id="panel-confirm-modal-heading"></span>
            </h5>
            <button type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <p id="panel-confirm-modal-message"></p>
          </div>
          <div class="modal-footer">
            <button type="button" id="panel-confirm-modal-cancel">İptal</button>
            <button type="button" id="panel-confirm-modal-submit">Onayla</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="panel-prompt-modal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="panel-prompt-modal-form">
            <div class="modal-header">
              <h5 class="modal-title">
                <i id="panel-prompt-modal-icon"></i>
                <span id="panel-prompt-modal-heading"></span>
              </h5>
              <button type="button" class="btn-close"></button>
            </div>
            <div class="modal-body">
              <label id="panel-prompt-modal-label" for="panel-prompt-modal-input"></label>
              <input type="text" id="panel-prompt-modal-input" />
              <div id="panel-prompt-modal-help" hidden></div>
            </div>
            <div class="modal-footer">
              <button type="button" id="panel-prompt-modal-cancel">İptal</button>
              <button type="submit" id="panel-prompt-modal-submit">Tamam</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="panel-alert-modal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <i id="panel-alert-modal-icon"></i>
              <span id="panel-alert-modal-heading"></span>
            </h5>
            <button type="button" class="btn-close"></button>
          </div>
          <div class="modal-body">
            <p id="panel-alert-modal-message"></p>
          </div>
          <div class="modal-footer">
            <button type="button" id="panel-alert-modal-submit">Tamam</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="panel-dialog-modal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="panel-dialog-modal-form">
            <div class="modal-header">
              <h5 class="modal-title">
                <i id="panel-dialog-modal-icon"></i>
                <span id="panel-dialog-modal-heading"></span>
              </h5>
              <button type="button" class="btn-close"></button>
            </div>
            <div class="modal-body" id="panel-dialog-modal-fields"></div>
            <div class="modal-footer">
              <button type="button" id="panel-dialog-modal-cancel">İptal</button>
              <button type="submit" id="panel-dialog-modal-submit">Tamam</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </body></html>`);
}

test("modal confirm resolves true when confirm button is clicked", async () => {
  const dom = createTestDom();
  const modal = createModalService({
    documentRef: dom.window.document,
    windowRef: dom.window,
  });

  const confirmPromise = modal.confirm({
    title: "Silme Onayı",
    message: "Bu içeriği silmek istiyor musunuz?",
    confirmText: "Evet, Sil",
    variant: "danger",
  });

  const modalEl = dom.window.document.getElementById("panel-confirm-modal");
  const headingEl = dom.window.document.getElementById("panel-confirm-modal-heading");
  const messageEl = dom.window.document.getElementById("panel-confirm-modal-message");
  const submitBtn = dom.window.document.getElementById("panel-confirm-modal-submit");

  assert.equal(headingEl.textContent, "Silme Onayı");
  assert.equal(messageEl.textContent, "Bu içeriği silmek istiyor musunuz?");
  assert.equal(submitBtn.textContent, "Evet, Sil");
  assert.ok(submitBtn.className.includes("btn-danger"));
  assert.ok(modalEl.classList.contains("show"));

  submitBtn.click();
  const result = await confirmPromise;
  assert.equal(result, true);
  assert.ok(!modalEl.classList.contains("show"));
});

test("modal confirm resolves false when cancel button or close button is clicked", async () => {
  const dom = createTestDom();
  const modal = createModalService({
    documentRef: dom.window.document,
    windowRef: dom.window,
  });

  const modalEl = dom.window.document.getElementById("panel-confirm-modal");
  const cancelBtn = dom.window.document.getElementById("panel-confirm-modal-cancel");

  const promise1 = modal.confirm("Basit onay");
  cancelBtn.click();
  assert.equal(await promise1, false);

  const closeBtn = modalEl.querySelector(".btn-close");
  const promise2 = modal.confirm("İkinci onay");
  closeBtn.click();
  assert.equal(await promise2, false);
});

test("modal prompt resolves with input value on submit and null on cancel", async () => {
  const dom = createTestDom();
  const modal = createModalService({
    documentRef: dom.window.document,
    windowRef: dom.window,
  });

  const modalEl = dom.window.document.getElementById("panel-prompt-modal");
  const formEl = dom.window.document.getElementById("panel-prompt-modal-form");
  const inputEl = dom.window.document.getElementById("panel-prompt-modal-input");
  const cancelBtn = dom.window.document.getElementById("panel-prompt-modal-cancel");

  const promise1 = modal.prompt({
    title: "Coin Fiyatı",
    message: "Fiyat giriniz:",
    defaultValue: "10",
    inputType: "number",
  });

  assert.equal(inputEl.value, "10");
  assert.equal(inputEl.type, "number");

  inputEl.value = "25";
  formEl.dispatchEvent(new dom.window.Event("submit", { bubbles: true, cancelable: true }));

  const val1 = await promise1;
  assert.equal(val1, "25");

  const promise2 = modal.prompt("Yeni isim:");
  cancelBtn.click();
  const val2 = await promise2;
  assert.equal(val2, null);
});

test("modal alert resolves when dismissed", async () => {
  const dom = createTestDom();
  const modal = createModalService({
    documentRef: dom.window.document,
    windowRef: dom.window,
  });

  const submitBtn = dom.window.document.getElementById("panel-alert-modal-submit");
  const messageEl = dom.window.document.getElementById("panel-alert-modal-message");

  const alertPromise = modal.alert({
    title: "Başarılı",
    message: "Kayıt başarıyla tamamlandı.",
  });

  assert.equal(messageEl.textContent, "Kayıt başarıyla tamamlandı.");
  submitBtn.click();
  await alertPromise;
});

test("modal dialog renders multiple fields and resolves with form data", async () => {
  const dom = createTestDom();
  const modal = createModalService({
    documentRef: dom.window.document,
    windowRef: dom.window,
  });

  const formEl = dom.window.document.getElementById("panel-dialog-modal-form");
  const dialogPromise = modal.dialog({
    title: "Bölüm Fiyatlandırma",
    confirmText: "Kaydet",
    fields: [
      { name: "coin_price", label: "Coin Fiyatı", type: "number", value: "15" },
      { name: "free_after", label: "Ücretsiz Olma Tarihi", type: "text", value: "2026-10-01" },
    ],
  });

  const priceInput = formEl.querySelector('[name="coin_price"]');
  const freeAfterInput = formEl.querySelector('[name="free_after"]');

  assert.ok(priceInput);
  assert.equal(priceInput.value, "15");
  assert.ok(freeAfterInput);
  assert.equal(freeAfterInput.value, "2026-10-01");

  priceInput.value = "50";
  formEl.dispatchEvent(new dom.window.Event("submit", { bubbles: true, cancelable: true }));

  const result = await dialogPromise;
  assert.deepEqual(result, {
    coin_price: "50",
    free_after: "2026-10-01",
  });
});

test("modal fallback uses native window dialogs when modal elements are absent", async () => {
  const emptyDom = new JSDOM("<!DOCTYPE html><html><body></body></html>");
  let nativeConfirmCalled = false;
  let nativePromptCalled = false;

  emptyDom.window.confirm = (msg) => {
    nativeConfirmCalled = true;
    return true;
  };
  emptyDom.window.prompt = (msg, def) => {
    nativePromptCalled = true;
    return "native-value";
  };

  const modal = createModalService({
    documentRef: emptyDom.window.document,
    windowRef: emptyDom.window,
  });

  const confirmRes = await modal.confirm("Do you agree?");
  assert.equal(confirmRes, true);
  assert.equal(nativeConfirmCalled, true);

  const promptRes = await modal.prompt("Enter text:", "default");
  assert.equal(promptRes, "native-value");
  assert.equal(nativePromptCalled, true);
});
