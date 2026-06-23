const STATUS_MAP = {
  // Approval / workflow
  pending:          { label: 'Pending',     className: 'badge-pending' },
  approved:         { label: 'Approved',    className: 'badge-success' },
  rejected:         { label: 'Rejected',    className: 'badge-danger' },
  under_review:     { label: 'Under Review',className: 'badge-info' },
  submitted:        { label: 'Submitted',   className: 'badge-info' },
  specification_approval: { label: 'Specification Committee', className: 'badge-warning' },
  tender_preparation: { label: 'Tender Preparation', className: 'badge-info' },
  advertised:       { label: 'Advertised',  className: 'badge-info' },
  bid_received:     { label: 'Bid Received', className: 'badge-info' },
  technical_evaluation: { label: 'TEC Evaluation', className: 'badge-purple' },
  bid_evaluation: { label: 'BEC Evaluation', className: 'badge-teal' },
  financial_evaluation: { label: 'Financial Review', className: 'badge-purple' },
  awarded:          { label: 'Awarded',     className: 'badge-success' },
  purchase_order_issued: { label: 'PO Issued', className: 'badge-success' },
  contract_signed:  { label: 'Contract Signed', className: 'badge-success' },
  on_hold:          { label: 'On Hold',     className: 'badge-warning' },
  in_progress:      { label: 'In Progress', className: 'badge-info' },
  completed:        { label: 'Completed',   className: 'badge-success' },
  cancelled:        { label: 'Cancelled',   className: 'badge-gray' },
  delayed:          { label: 'Delayed',     className: 'badge-danger' },
  skipped:          { label: 'Skipped',     className: 'badge-gray' },
  draft:            { label: 'Draft',       className: 'badge-gray' },
  active:           { label: 'Active',      className: 'badge-success' },
  inactive:         { label: 'Inactive',    className: 'badge-gray' },
  // Role display
  director:                { label: 'Director',               className: 'badge-purple' },
  procurement_officer:     { label: 'Procurement Officer',    className: 'badge-info' },
  tec_member:              { label: 'TEC Member',             className: 'badge-teal' },
  bec_member:              { label: 'BEC Member',             className: 'badge-teal' },
  specification_committee: { label: 'Specification Committee',className: 'badge-warning' },
  accountant:              { label: 'Accountant',             className: 'badge-purple' },
  it_admin:                { label: 'IT Admin',               className: 'badge-danger' },
};

export default function StatusBadge({ status }) {
  const config = STATUS_MAP[status] || { label: status, className: 'badge-gray' };
  return (
    <span className={`badge ${config.className}`}>
      {config.label}
    </span>
  );
}
