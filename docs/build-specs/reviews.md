# Build spec: reviews slice

Exemplar to replicate: people. Schema table: `reviews` (view `mcp_reviews`, function `hr_can_see_review`). Never modify it.

## Screens
| Screen id | Canonical URL | Purpose |
|---|---|---|
| `reviews-list` | `/reviews/` | Cards (a named thing per card: person, kind, due date, reviewer, status, rating stars when completed); filters status/person/reviewer/overdue; default: mine to write, then about me |
| `review-add` | `/reviews/new` | Schedule form |
| `review-view` | `/reviews/{id}` | The review: header facts, the five texts, rating, employee comment; buttons by state: **Write** (reviewer/hr) → edit; **Complete** with "share with the person" checkbox (confirm); **Cancel**; the person's **Comment** form once shared (one comment; editable until… never — one comment, logged) |
| `review-edit` | `/reviews/{id}/edit` | rating (1–5 radio), summary, strengths, improvements, goals; saving moves `scheduled` → `in_progress` |

## Forms
- Schedule: person (select2 of active humans with employment), kind (annual/probation/quarterly/other), due_date (required), reviewer (select2; default the person's manager; not the person), period_start, period_end. ids `review-form-field-*`.
- Write: the five fields. Complete: `share` checkbox → `shared_at = now()`. Comment: `comment` textarea (required). Cancel: reason.

## Files
- `html/reviews/index.php` · `view.php` · `form.php` · `save.php` · `complete.php` · `comment.php` · `cancel.php`
- `app/features/reviews/queries.php` · `present.php`
- `app/views/reviews/page.php` · `view.php` · `partials/cards.php` · `card.php` · `form.php` · `write-form.php` · `comment-form.php` · `saved.php`

## Query functions
`find_reviews(PDO, array $filters, int $page)` · `find_review(PDO, int $id)` · `schedule_review(PDO, …)` · `save_review(PDO, int $id, array $fields)` · `complete_review(PDO, int $id, bool $share, int $actorId)` · `comment_review(PDO, int $id, string $comment, int $memberId)` · `cancel_review(PDO, int $id, ?string $reason, int $actorId)` · `reviews_overdue(PDO)` (dashboard)

## Gates
Schedule: `hr`, or manager of the person. Write/complete/cancel: the reviewer or `hr`. Comment: the person, once `shared_at` is set, once. Read: as `hr_can_see_review`.

## Action manifest entries
`review_schedule`, `review_save` (partial), `review_complete`, `review_comment`, `review_cancel`; the screens above.

## Activity log events
`review.schedule`, `review.save` (after: which fields changed — never the text), `review.complete` (after: shared, rating), `review.comment`, `review.cancel`, `screen.view`.

## Status vocabulary
scheduled → info · in_progress → warning · completed → success · cancelled → secondary; overdue (due < today and not completed) → danger badge.

## Out of scope
Goals as records; 360 feedback; templates; reminders by mail.

## Open Questions
- (none)
