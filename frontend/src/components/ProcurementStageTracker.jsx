import StatusBadge from './StatusBadge';
import Icon from './Icon';

const DEFAULT_STEPS = [
  { key: 'procurement_officer', label: 'Procurement Officer', status: 'draft', icon: 'procurement' },
  { key: 'specification_committee', label: 'Specification Committee', status: 'specification_approval', icon: 'document' },
  { key: 'tender_preparation', label: 'Tender Preparation / Calling', status: 'tender_preparation', icon: 'announcement' },
  { key: 'bec', label: 'BEC', status: 'bid_evaluation', icon: 'document' },
  { key: 'accountant', label: 'Accountant / Financial Review', status: 'financial_evaluation', icon: 'finance' },
  { key: 'purchase_order', label: 'Purchase Order / Award', status: 'purchase_order_issued', icon: 'purchase' },
  { key: 'completed', label: 'Completed', status: 'completed', icon: 'completed' },
];

function normalizeSteps(steps, currentStage) {
  if (Array.isArray(steps) && steps.length > 0) return steps;

  const currentKey = currentStage?.key || 'procurement_officer';
  const currentIndex = DEFAULT_STEPS.findIndex((step) => step.key === currentKey);

  return DEFAULT_STEPS.map((step, index) => ({
    ...step,
    state:
      currentIndex === -1
        ? 'pending'
        : index < currentIndex
          ? 'completed'
          : index === currentIndex
            ? 'current'
            : 'pending',
  }));
}

export default function ProcurementStageTracker({ currentStage, workflowSteps }) {
  const steps = normalizeSteps(workflowSteps, currentStage);

  return (
    <div className="tracking-panel">
      <div className="tracking-current-card">
        <div className="tracking-current-icon"><Icon name={currentStage?.icon || 'location'} size={28} /></div>
        <div>
          <div className="tracking-label">Current Location</div>
          <div className="tracking-title">{currentStage?.label || 'Procurement Officer'}</div>
          <div className="tracking-text">
            {currentStage?.description || 'Procurement tracking stage is being updated.'}
          </div>
          {currentStage?.status && (
            <div style={{ marginTop: '8px' }}>
              <StatusBadge status={currentStage.status} />
            </div>
          )}
        </div>
      </div>

      <div className="tracking-steps">
        {steps.map((step) => (
          <div key={step.key} className={`tracking-step ${step.state || 'pending'}`}>
            <div className="tracking-step-icon"><Icon name={step.icon || 'location'} size={18} /></div>
            <div className="tracking-step-label">{step.label}</div>
            <div className="tracking-step-state">
              {step.state === 'completed' ? 'Done' : step.state === 'current' ? 'Now' : 'Pending'}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
