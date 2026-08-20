import Layout from '../../components/Layout';

const PERMISSIONS_MAP = {
  director: ['View All Procurements', 'Track Procurement Progress', 'View Reports', 'View Audit Logs', 'View Purchase Orders'],
  procurement_officer: ['Create Procurement', 'Edit Procurement', 'Delete Procurement', 'View Purchase Orders'],
  bec_member: ['View Assigned Evaluations', 'Submit Bid Evaluation'],
  specification_committee: ['View Specifications', 'Submit Specification Review'],
  accountant: ['View Financial Requests', 'Approve/Reject Financial Requests'],
  it_admin: ['Manage Users', 'Assign Roles', 'System Settings', 'Backup Management', 'View All'],
};

export default function RolePermissionManagement() {
  return (
    <Layout>
      <div className="page-wrapper animate-fade-in">
        <div className="page-title"> Roles & Permissions</div>
        <div className="page-subtitle">Overview of role-based access control in the system</div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: '16px' }}>
          {Object.entries(PERMISSIONS_MAP).map(([role, permissions]) => (
            <div key={role} className="card">
              <div style={{
                fontWeight: 700, fontSize: '0.9rem', marginBottom: '14px',
                padding: '6px 14px', background: 'var(--primary-light)',
                borderRadius: '6px', color: 'var(--primary)',
                display: 'inline-block',
              }}>
                {role.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
              </div>
              <ul style={{ listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '8px' }}>
                {permissions.map((perm) => (
                  <li key={perm} style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '0.875rem', color: 'var(--text-dark)' }}>
                    <span style={{ color: 'var(--success)', fontSize: '0.9rem' }}></span>
                    {perm}
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      </div>
    </Layout>
  );
}
