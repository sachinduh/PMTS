import { useEffect, useState } from 'react';
import Layout from '../../components/Layout';

export default function ReportViewer() {
  const [reports, setReports] = useState([]);
  const API = 'http://localhost/PMTS/backend/reports';
  useEffect(()=>{ fetch(`${API}/get_reports.php`).then(r=>r.json()).then(d=>setReports(d.reports || [])).catch(()=>{}); }, []);
  return <Layout><div className="page-wrapper animate-fade-in"><div className="page-title"> Report Viewer</div><div className="page-subtitle">View and download generated procurement reports</div><div className="card" style={{padding:20}}><table className="data-table"><thead><tr><th>Title</th><th>Type</th><th>Created</th><th>Actions</th></tr></thead><tbody>{reports.map(r=><tr key={r.id}><td>{r.report_title}</td><td>{r.report_type}</td><td>{r.created_at}</td><td><a className="action-btn edit" href={`${API}/download_pdf.php?id=${r.id}`} target="_blank">PDF/Print</a><a className="action-btn approve" href={`${API}/download_excel.php?id=${r.id}`} target="_blank">Excel CSV</a></td></tr>)}</tbody></table></div></div></Layout>;
}
