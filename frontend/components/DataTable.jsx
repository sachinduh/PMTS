import { useState } from 'react';
import Icon from './Icon';

export default function DataTable({ columns = [], data = [], loading = false, emptyMessage = 'No records found.' }) {
  const [search, setSearch] = useState('');

  const filtered = search
    ? data.filter((row) =>
        Object.values(row).some((val) =>
          String(val ?? '').toLowerCase().includes(search.toLowerCase())
        )
      )
    : data;

  return (
    <div>
      {/* Toolbar */}
      <div className="table-toolbar">
        <div className="search-input-wrapper">
          <span className="search-icon"><Icon name="search" size={16} /></span>
          <input
            className="search-input"
            type="text"
            placeholder="Search..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
      </div>

      {/* Table */}
      <div className="table-wrapper">
        {loading ? (
          <div className="loading-page">
            <div className="spinner" />
          </div>
        ) : (
          <table className="data-table">
            <thead>
              <tr>
                {columns.map((col) => (
                  <th key={col.key}>{col.label}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {filtered.length === 0 ? (
                <tr>
                  <td colSpan={columns.length}>
                    <div className="empty-state">
                      <div className="empty-state-icon"><Icon name="empty" size={34} /></div>
                      <div className="empty-state-text">{emptyMessage}</div>
                    </div>
                  </td>
                </tr>
              ) : (
                filtered.map((row, idx) => (
                  <tr key={row.id ?? idx}>
                    {columns.map((col) => (
                      <td key={col.key}>
                        {col.render ? col.render(row[col.key], row) : row[col.key] ?? '—'}
                      </td>
                    ))}
                  </tr>
                ))
              )}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
