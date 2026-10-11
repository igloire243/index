import { reactive } from 'vue';
import { api } from '../api';

export const finance = reactive({
    loaded: false,
    loading: false,
    accounts: [],
    debts: [],
    assets: [],
    settings: { base_currency: 'USD', rates: {} },
});

export async function loadFinance(force = false) {
    if (finance.loading || (finance.loaded && !force)) return;
    finance.loading = true;
    try {
        const data = await api('finance');
        finance.accounts = data.accounts;
        finance.debts = data.debts;
        finance.assets = data.assets;
        finance.settings = { base_currency: data.settings.base_currency, rates: { ...(data.settings.rates || {}) } };
        finance.loaded = true;
    } finally {
        finance.loading = false;
    }
}

/* ----- Comptes ----- */

export async function saveAccount(data, id = null) {
    const saved = await api(id ? `accounts/${id}` : 'accounts', { method: id ? 'PUT' : 'POST', body: data });
    const i = finance.accounts.findIndex((a) => a.id === saved.id);
    if (i >= 0) finance.accounts[i] = saved;
    else finance.accounts.push(saved);
    return saved;
}

export async function archiveAccount(id) {
    await api(`accounts/${id}`, { method: 'DELETE' });
    finance.accounts = finance.accounts.filter((a) => a.id !== id);
}

const replaceAccount = (acc) => {
    const i = finance.accounts.findIndex((a) => a.id === acc.id);
    if (i >= 0) finance.accounts[i] = acc;
};

export async function addMovement(accountId, data) {
    replaceAccount(await api(`accounts/${accountId}/movements`, { method: 'POST', body: data }));
}

export async function adjustAccount(accountId, data) {
    replaceAccount(await api(`accounts/${accountId}/adjust`, { method: 'POST', body: data }));
}

export async function transfer(data) {
    const { from, to } = await api('accounts/transfer', { method: 'POST', body: data });
    replaceAccount(from);
    replaceAccount(to);
}

export const loadMovements = (accountId) => api(`accounts/${accountId}/movements`);

export async function deleteMovement(id) {
    await api(`movements/${id}`, { method: 'DELETE' });
    await loadFinance(true); // les soldes ont changé (un virement touche deux comptes)
}

/* ----- Créances et dettes ----- */

const replaceDebt = (debt) => {
    const i = finance.debts.findIndex((d) => d.id === debt.id);
    if (i >= 0) finance.debts[i] = debt;
    else finance.debts.unshift(debt);
};

export async function saveDebt(data, id = null) {
    const saved = await api(id ? `debts/${id}` : 'debts', { method: id ? 'PUT' : 'POST', body: data });
    replaceDebt(saved);
    return saved;
}

export async function removeDebt(id) {
    await api(`debts/${id}`, { method: 'DELETE' });
    finance.debts = finance.debts.filter((d) => d.id !== id);
}

export async function addPayment(debtId, data) {
    replaceDebt(await api(`debts/${debtId}/payments`, { method: 'POST', body: data }));
    if (data.account_id) await loadFinance(true); // le solde du compte a changé
}

export async function removePayment(debtId, paymentId) {
    replaceDebt(await api(`debts/${debtId}/payments/${paymentId}`, { method: 'DELETE' }));
    await loadFinance(true);
}

/* ----- Biens ----- */

export async function saveAsset(data, id = null) {
    const saved = await api(id ? `assets/${id}` : 'assets', { method: id ? 'PUT' : 'POST', body: data });
    const i = finance.assets.findIndex((a) => a.id === saved.id);
    if (i >= 0) finance.assets[i] = saved;
    else finance.assets.push(saved);
    return saved;
}

export async function removeAsset(id) {
    await api(`assets/${id}`, { method: 'DELETE' });
    finance.assets = finance.assets.filter((a) => a.id !== id);
}

/* ----- Taux de change ----- */

export async function saveSettings(data) {
    const saved = await api('finance/settings', { method: 'PUT', body: data });
    finance.settings = { base_currency: saved.base_currency, rates: { ...(saved.rates || {}) } };
}
