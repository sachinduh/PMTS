<?php
// ============================================================
// PMTS procurement tracking helper
// Converts workflow status into human-readable current location.
// ============================================================

function pmtsWorkflowStages(): array
{
    return [
        [
            'key' => 'procurement_officer',
            'label' => 'Procurement Officer',
            'status' => 'draft',
            'statuses' => ['draft', 'submitted', 'under_review'],
            'icon' => 'procurement',
            'description' => 'Procurement created and basic details are being prepared by the Procurement Officer.',
        ],
        [
            'key' => 'specification_committee',
            'label' => 'Specification Committee',
            'status' => 'specification_approval',
            'statuses' => ['specification_approval'],
            'icon' => 'document',
            'description' => 'Specification Committee is preparing or reviewing the procurement specification.',
        ],
        [
            'key' => 'tender_preparation',
            'label' => 'Tender Preparation / Calling',
            'status' => 'tender_preparation',
            'statuses' => ['tender_preparation', 'advertised', 'bid_received'],
            'icon' => 'announcement',
            'description' => 'Tender documents, calling, advertising, bid closing, and bid opening activities are in progress.',
        ],
        [
            'key' => 'tec',
            'label' => 'TEC',
            'status' => 'technical_evaluation',
            'statuses' => ['technical_evaluation'],
            'icon' => 'search',
            'description' => 'Technical Evaluation Committee is handling the technical evaluation.',
        ],
        [
            'key' => 'bec',
            'label' => 'BEC',
            'status' => 'bid_evaluation',
            'statuses' => ['bid_evaluation'],
            'icon' => 'document',
            'description' => 'Bid Evaluation Committee is handling bid evaluation and recommendation.',
        ],
        [
            'key' => 'accountant',
            'label' => 'Accountant / Financial Review',
            'status' => 'financial_evaluation',
            'statuses' => ['financial_evaluation'],
            'icon' => 'finance',
            'description' => 'Accountant is checking financial approval/payment related details.',
        ],
        [
            'key' => 'purchase_order',
            'label' => 'Purchase Order / Award',
            'status' => 'purchase_order_issued',
            'statuses' => ['awarded', 'purchase_order_issued', 'contract_signed'],
            'icon' => 'purchase',
            'description' => 'Award, purchase order, or contract related activities are in progress.',
        ],
        [
            'key' => 'completed',
            'label' => 'Completed',
            'status' => 'completed',
            'statuses' => ['completed'],
            'icon' => 'completed',
            'description' => 'Procurement is completed.',
        ],
    ];
}

function pmtsTrackingStageForStatus(?string $status): array
{
    $status = $status ?: 'draft';

    foreach (pmtsWorkflowStages() as $index => $stage) {
        if (in_array($status, $stage['statuses'], true)) {
            return [
                'key' => $stage['key'],
                'label' => $stage['label'],
                'status' => $status,
                'display_status' => $stage['status'],
                'icon' => $stage['icon'],
                'description' => $stage['description'],
                'step' => $index + 1,
                'total_steps' => count(pmtsWorkflowStages()),
            ];
        }
    }

    if ($status === 'cancelled') {
        return [
            'key' => 'cancelled',
            'label' => 'Cancelled',
            'status' => $status,
            'display_status' => $status,
            'icon' => 'error',
            'description' => 'Procurement has been cancelled.',
            'step' => 0,
            'total_steps' => count(pmtsWorkflowStages()),
        ];
    }

    if ($status === 'on_hold') {
        return [
            'key' => 'on_hold',
            'label' => 'On Hold',
            'status' => $status,
            'display_status' => $status,
            'icon' => 'pending',
            'description' => 'Procurement is currently on hold.',
            'step' => 0,
            'total_steps' => count(pmtsWorkflowStages()),
        ];
    }

    return [
        'key' => 'unknown',
        'label' => 'Unknown Stage',
        'status' => $status,
        'display_status' => $status,
        'icon' => 'help',
        'description' => 'Current tracking stage is not mapped yet.',
        'step' => 0,
        'total_steps' => count(pmtsWorkflowStages()),
    ];
}

function pmtsWorkflowStepsForStatus(?string $status): array
{
    $current = pmtsTrackingStageForStatus($status);
    $currentStep = (int) ($current['step'] ?? 0);

    $steps = [];
    foreach (pmtsWorkflowStages() as $index => $stage) {
        $stepNumber = $index + 1;
        $state = 'pending';
        if ($currentStep > 0 && $stepNumber < $currentStep) {
            $state = 'completed';
        } elseif ($currentStep > 0 && $stepNumber === $currentStep) {
            $state = 'current';
        }

        $steps[] = [
            'key' => $stage['key'],
            'label' => $stage['label'],
            'status' => $stage['status'],
            'icon' => $stage['icon'],
            'description' => $stage['description'],
            'state' => $state,
            'step' => $stepNumber,
        ];
    }

    return $steps;
}

function pmtsEnrichProcurementTracking(array $procurement): array
{
    $status = $procurement['current_status'] ?? $procurement['status'] ?? 'draft';
    $stage = pmtsTrackingStageForStatus($status);

    $procurement['status'] = $status;
    $procurement['current_stage_key'] = $stage['key'];
    $procurement['current_stage_label'] = $stage['label'];
    $procurement['current_location'] = $stage['label'];
    $procurement['current_stage_description'] = $stage['description'];
    $procurement['tracking_stage'] = $stage;

    return $procurement;
}
