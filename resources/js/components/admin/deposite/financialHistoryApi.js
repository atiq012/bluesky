import axiosInstance from '../../../axiosInstance';
import moment from 'moment';

export function formatStatementMoney(v) {
    const n = Number(v ?? 0);
    return n.toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export async function fetchAgentBalance() {
    const res = await axiosInstance.get('agent/balance');
    return res.data?.data || {
        net_balance: 0,
        credit_balance: 0,
        cash_portion: 0,
        credit_taken_total: 0,
        cash_deposited_total: 0,
    };
}

export async function fetchAgentStatement(fromDate = '', toDate = '') {
    const params = {};
    if (fromDate) params.from = moment(fromDate, 'DD-MMM-YYYY').format('YYYY-MM-DD');
    if (toDate) params.to = moment(toDate, 'DD-MMM-YYYY').format('YYYY-MM-DD');
    const res = await axiosInstance.get('agent/statement', { params });
    const items = res.data?.data?.data || [];
    return items.map((row) => ({
        ...row,
        date: row.transaction_at ? moment(row.transaction_at).format('DD-MMM-YYYY HH:mm') : '-',
        money_in: row.direction === 'in' ? formatStatementMoney(row.amount) : '-',
        money_out: row.direction === 'out' ? formatStatementMoney(row.amount) : '-',
        balance: formatStatementMoney(row.net_balance_after),
        credit_due: formatStatementMoney(row.credit_balance_after),
    }));
}

export async function fetchFinancialHistory(fromDate = '', toDate = '') {
    const [balance, rows] = await Promise.all([
        fetchAgentBalance(),
        fetchAgentStatement(fromDate, toDate),
    ]);
    return { balance, rows };
}
