# M08 — Approval Workflow Engine

| | |
|--|--|
| **Developer** | Dev 2 |
| **Phase** | 2 |
| **Depends on** | M00, M01 |
| **Blocks** | M09, M13 |
| **Parallel with** | M02, M03, M04, M11 |

## Scope

Generic multi-level approval engine for transfers (all movement types), tag replacement, kit/bundle assignments, and disposal.

## DB Tables

- `approval_workflows` (name, module enum: transfer/disposal/tag_replacement/kit_assignment, is_active)
- `approval_steps` (workflow_id, level, approver_type: role/user, approver_id, escalation_hours)
- `approval_requests` (workflow_id, approvable morph, status, current_step, submitted_by)
- `approval_actions` (request_id, step_level, user_id, action enum, comment, acted_at)

## Service

`WorkflowService::submit($model, $module)`, `approve($request)`, `reject($request)`, `escalate()`

## Routes

| Method | URI | Action |
|--------|-----|--------|
| CRUD | `/admin/workflows` | WorkflowController |
| GET | `/approvals` | Approver inbox |
| POST | `/approvals/{request}/approve` | Approve |
| POST | `/approvals/{request}/reject` | Reject |

## Tasks

- [ ] Workflow + step admin CRUD
- [ ] Polymorphic approval request creation
- [ ] Multi-level sequential approval logic
- [ ] Escalation scheduled job (daily)
- [ ] Approver inbox UI with pending count in nav
- [ ] Email + in-app notification on pending step (via M12 stub)
- [ ] Seed default transfer workflow: Dept Head → Asset Manager
- [ ] Seed default tag replacement workflow: Asset Manager → Super Admin
- [ ] On `tag_replacement` approval final step → call `TagService::applyReplacement()`
- [ ] Permission: `workflow.approve`, `workflows.manage`

## Acceptance criteria

- Configurable workflow without code changes
- Approve advances step; final approval returns success to caller
- Reject stops workflow; source record unchanged
- Escalation fires after configured hours

## Handoff

- M09 calls `WorkflowService::submit($movement, 'transfer')` on movement submit
- M05 calls `WorkflowService::submit($replacement, 'tag_replacement')` on tag replace submit
- M17 calls `WorkflowService::submit($kitAssignment, 'kit_assignment')` when approval mode is `single`; per-asset mode uses `transfer` per item
