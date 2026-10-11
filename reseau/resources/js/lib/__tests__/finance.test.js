import { describe, expect, it } from 'vitest';
import { assetGaps, convert, dueLabel, dueSoon, fmtMoney, netWorth, sumBase, toBase, totalsByCurrency } from '../finance';

const settings = { base_currency: 'USD', rates: { CDF: 2800, EUR: 0.9 } };
const acc = (balance, currency = 'USD') => ({ balance, currency });
const debt = (o) => ({ direction: 'owed_to_me', amount: 100, remaining: 100, currency: 'USD', due_on: null, ...o });
const asset = (o) => ({ category: 'terrain', status: 'owned', value: 1000, currency: 'USD', ...o });

describe('conversion', () => {
    it('garde la devise de référence et convertit les autres avec « 1 référence = N devise »', () => {
        expect(toBase(50, 'USD', settings)).toBe(50);
        expect(toBase(28000, 'CDF', settings)).toBeCloseTo(10);
        expect(toBase(9, 'EUR', settings)).toBeCloseTo(10);
    });

    it('renvoie null quand le taux est inconnu ou invalide', () => {
        expect(toBase(10, 'XAF', settings)).toBeNull();
        expect(toBase(10, 'XAF', { base_currency: 'USD', rates: { XAF: 0 } })).toBeNull();
        expect(toBase(10, 'XAF', { base_currency: 'USD' })).toBeNull();
    });

    it('additionne et signale les devises sans taux au lieu de les compter pour zéro en silence', () => {
        const r = sumBase([{ amount: 10, currency: 'USD' }, { amount: 5600, currency: 'CDF' }, { amount: 7, currency: 'XAF' }], settings);
        expect(r.total).toBeCloseTo(12);
        expect(r.missing).toEqual(['XAF']);
    });

    it('totalise par devise sans conversion', () => {
        const t = totalsByCurrency([{ amount: 5, currency: 'USD' }, { amount: 1000, currency: 'CDF' }, { amount: 7, currency: 'USD' }]);
        expect(t).toEqual([{ currency: 'CDF', total: 1000 }, { currency: 'USD', total: 12 }]);
    });
});

describe('convert', () => {
    it('passe par la devise de référence dans les deux sens', () => {
        expect(convert(10, 'USD', 'CDF', settings)).toBeCloseTo(28000);
        expect(convert(28000, 'CDF', 'USD', settings)).toBeCloseTo(10);
        expect(convert(28000, 'CDF', 'EUR', settings)).toBeCloseTo(9);
        expect(convert(5, 'EUR', 'EUR', settings)).toBe(5);
    });

    it('renvoie null si un taux manque', () => {
        expect(convert(10, 'USD', 'XAF', settings)).toBeNull();
        expect(convert(10, 'XAF', 'USD', settings)).toBeNull();
    });
});

describe('netWorth', () => {
    it('additionne fonds, créances et biens, et retranche les dettes', () => {
        const w = netWorth({
            accounts: [acc(1000), acc(2800000, 'CDF')],               // 1 000 + 1 000
            debts: [debt({ remaining: 300 }), debt({ direction: 'i_owe', remaining: 500 })],
            assets: [asset({ value: 5000 }), asset({ status: 'wanted', value: 99999 })], // les envies ne comptent pas
        }, settings);

        expect(w.funds).toBeCloseTo(2000);
        expect(w.receivables).toBe(300);
        expect(w.debts).toBe(500);
        expect(w.assets).toBe(5000);
        expect(w.net).toBeCloseTo(6800);
        expect(w.missing).toEqual([]);
    });

    it('ignore les créances et dettes soldées', () => {
        const w = netWorth({ accounts: [], debts: [debt({ remaining: 0 }), debt({ direction: 'i_owe', remaining: 0 })], assets: [] }, settings);
        expect(w.net).toBe(0);
    });

    it('ignore un bien sans valeur et liste les devises sans taux', () => {
        const w = netWorth({ accounts: [acc(100, 'XAF')], debts: [], assets: [asset({ value: null })] }, settings);
        expect(w.net).toBe(0);
        expect(w.missing).toEqual(['XAF']);
    });
});

describe('échéances', () => {
    const today = '2026-10-12';
    const debts = [
        debt({ id: 1, due_on: '2026-10-05' }),                       // en retard de 7 j
        debt({ id: 2, due_on: '2026-10-20' }),                       // dans 8 j
        debt({ id: 3, due_on: '2026-12-01' }),                       // trop loin
        debt({ id: 4, due_on: null }),                               // pas d'échéance
        debt({ id: 5, due_on: '2026-10-01', remaining: 0 }),         // soldée
    ];

    it('garde les échéances proches ou dépassées, les plus urgentes d’abord', () => {
        expect(dueSoon(debts, today).map((d) => [d.id, d.days])).toEqual([[1, -7], [2, 8]]);
        expect(dueSoon(debts, today, 3).map((d) => d.id)).toEqual([1]);
    });

    it('formule le délai', () => {
        expect(dueLabel(-3)).toBe('en retard de 3 j');
        expect(dueLabel(0)).toBe('échéance aujourd’hui');
        expect(dueLabel(1)).toBe('demain');
        expect(dueLabel(9)).toBe('dans 9 j');
    });
});

describe('assetGaps', () => {
    it('distingue possédé, souhaité et pas encore', () => {
        const gaps = Object.fromEntries(assetGaps([asset({ category: 'terrain' }), asset({ category: 'vehicule', status: 'wanted' })]).map((g) => [g.key, g.state]));
        expect(gaps.terrain).toBe('owned');
        expect(gaps.vehicule).toBe('wanted');
        expect(gaps.residence).toBe('missing');
    });

    it('propose tous les types, chacun avec une explication', () => {
        const gaps = assetGaps([]);
        expect(gaps).toHaveLength(14);
        expect(gaps.every((g) => g.state === 'missing' && g.why.length > 10 && g.emoji)).toBe(true);
    });
});

describe('fmtMoney', () => {
    it('formate les devises ISO et retombe sur le code pour les autres', () => {
        expect(fmtMoney(1500, 'USD')).toMatch(/1\s?500/);
        expect(fmtMoney(12.5, 'USD')).toMatch(/12,50/);
        expect(fmtMoney(3, 'USDT')).toContain('USDT');
        expect(fmtMoney(null, 'USD')).toBe('–');
    });
});
