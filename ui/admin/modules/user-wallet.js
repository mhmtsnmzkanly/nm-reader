/** Wallet balance, transactions and package operations. */
export function createUserWalletController({
  store, api, responseItems, responseMeta, getPageEpoch, assertCurrentPage,
  hasPermission, mountPage, mountPartial, bindFormAction, registerPageCleanup,
  showToast, renderPager,
  translate = (_key, fallback) => fallback,
  formatNumber = (value) => String(Number(value || 0)),
} = {}) {
  async function loadUserWalletPage(userId, pageNumber = 1) {
    const requestEpoch = getPageEpoch();
    if (!userId) {
      throw new Error(
        translate("admin.user.id_missing", "Kullanıcı kimliği bulunamadı"),
      );
    }
    const canManageWallet = hasPermission("admin.wallet.manage");
    const canLoadPackages = canManageWallet &&
      hasPermission("admin.shop.manage");
    const [walletResponse, transactionResponse, packageResponse, userResponse] =
      await Promise.all([
        api(`/wallets/${encodeURIComponent(userId)}`),
        api(
          `/wallets/${encodeURIComponent(userId)}/transactions?page=${
            Math.max(1, Number(pageNumber))
          }&per_page=25`,
        ),
        canLoadPackages
          ? api("/shop/packages?per_page=100")
          : Promise.resolve(null),
        hasPermission("admin.users.manage")
          ? api(`/users/${encodeURIComponent(userId)}/overview`).catch(() =>
            null
          )
          : Promise.resolve(null),
      ]);
    assertCurrentPage(requestEpoch);
    const wallet = walletResponse?.data || {};
    const user = userResponse?.data?.user || {};
    const transactions = responseItems(transactionResponse).map((item) => ({
      ...item,
      coin_delta: Number(item.coin_delta || 0),
      delta_prefix: Number(item.coin_delta) >= 0 ? "+" : "",
      delta_class: Number(item.coin_delta) >= 0
        ? "text-success"
        : "text-danger",
      balance_after: formatNumber(item.balance_after),
      reference_label:
        [item.reference_type, item.reference_id].filter(Boolean).join(":") ||
        "-",
    }));
    const packages = responseItems(packageResponse).map((item) => ({
      ...item,
      total_coin: Number(item.total_coin || item.coin_amount || 0),
    }));
    store.batch(() => {
      store.set("userWalletId", userId);
      store.set("userWallet", wallet);
      store.set("userWalletMeta", responseMeta(transactionResponse));
    });
    const page = mountPage("panel-user-wallet-content", {
      user_id: encodeURIComponent(userId),
      username: user.username || userId,
      header: {
        title: translate("admin.user.wallet_title", "Kullanıcı cüzdanı"),
        description: translate(
          "admin.user.wallet_description",
          "Kullanıcının coin hareketleri ve bakiyesi",
        ),
        parent_path: `/panel/user/${encodeURIComponent(userId)}`,
        show_back: true,
      },
      balance_coin: formatNumber(wallet.balance_coin),
      total_coin_purchased: formatNumber(wallet.total_coin_purchased),
      total_coin_spent: formatNumber(wallet.total_coin_spent),
      can_manage_wallet: canManageWallet,
      can_load_packages: canLoadPackages,
      packages,
      transactions,
      has_transactions: transactions.length > 0,
    });
    mountPartial(
      "panel-rows-wallet-transactions",
      page.querySelector("#panel-user-wallet-transactions"),
      { items: transactions, has_items: transactions.length > 0 },
    );
    const walletForm = page.querySelector("#panel-user-wallet-form");
    if (walletForm && bindFormAction) registerPageCleanup?.(bindFormAction(walletForm, async (data) => {
        const action = String(data.get("wallet_action")) === "debit"
          ? "debit"
          : "credit";
        await api(`/wallets/${encodeURIComponent(userId)}/${action}`, {
          method: "POST",
          body: {
            amount: Number(data.get("amount")),
            reason: String(data.get("reason") || ""),
          },
        });
        showToast(translate("admin.user.wallet_updated", "Cüzdan güncellendi"));
        await loadUserWalletPage(
          userId,
          Number(store.get("userWalletMeta")?.page || 1),
        );
      }, { onError: (error) => showToast(error.message, "danger") }));
    const grantPackageForm = page.querySelector(
      "#panel-user-wallet-package-form",
    );
    if (grantPackageForm && bindFormAction) registerPageCleanup?.(bindFormAction(grantPackageForm, async (data) => {
        await api(`/wallets/${encodeURIComponent(userId)}/grant-package`, {
          method: "POST",
          body: {
            package_id: Number(data.get("package_id")),
            cash_amount: String(data.get("cash_amount") || ""),
            reason: String(data.get("grant_reason") || ""),
          },
        });
        showToast(translate("admin.user.package_granted", "Paket kullanıcıya tanımlandı"));
        await loadUserWalletPage(
          userId,
          Number(store.get("userWalletMeta")?.page || 1),
        );
      }, { onError: (error) => showToast(error.message, "danger") }));
    renderPager(
      "panel-user-wallet-pager",
      store.get("userWalletMeta"),
      "previousUserWalletPage",
      "nextUserWalletPage",
    );
  }

  return Object.freeze({ loadUserWalletPage });
}
