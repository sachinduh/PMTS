import { useEffect, useState } from 'react';
import Layout from '../../components/Layout';
import api, { API_BASE_URL } from '../../api/api';

export default function ReportViewer() {
  const [reports, setReports] = useState([]);

  useEffect(() => {
    api.get('/reports/get_reports.php')
      .then((response) => {
        const data = response.data || {};
        setReports(data.data || data.reports || []);
      })
      .catch(() => setReports([]));
  }, []);

  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="page-title">Report Viewer</div>
        <div className="page-subtitle">View and download generated procurement reports</div>
        <div className="card" style={{ padding: 20 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {reports.map((report) => (
                <tr key={report.id}>
                  <td>{report.report_title}</td>
                  <td>{report.report_type}</td>
                  <td>{report.created_at}</td>
                  <td>
                    <a
                      className="action-btn edit"
                      href={`${API_BASE_URL}/reports/download_pdf.php?id=${report.id}`}
                      target="_blank"
                      rel="noreferrer"
                    >
                      PDF/Print
                    </a>
                    <a
                      className="action-btn approve"
                      href={`${API_BASE_URL}/reports/download_excel.php?id=${report.id}`}
                    >
                      Excel CSV
                    </a>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </Layout>
  );
}
