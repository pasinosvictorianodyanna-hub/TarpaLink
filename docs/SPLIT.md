# Work split

Two developers, one Laravel application. The split is by **surface**, not by feature:
one person owns everything the customer sees, the other owns everything the shop and
the platform see.

> **Status:** every task in both tables below is built. This document stays as the
> record of how the work was divided and why the spine had to come first.

| | Dev A | Dev B |
|---|---|---|
| Surface | Landing + customer storefront | Shop panel + admin panel |
| Framework | Blade + Vue 3 | Filament (Livewire) |
| Reads | price matrix, jobs, proofs | everything |
| Writes | jobs, proofs, payments | shops, price matrix, content, payouts |

That part is clean — the two halves use different frameworks, so you won't collide in
the same files. **But the dependency runs the wrong way, and that is the thing to fix
before writing a line of UI.**

## The trap

The customer side *consumes* the price matrix. The shop panel *defines* it.

If Dev A starts on the order builder today, there is no `price_tiers` table, no
`PriceCalculator`, and no shops to quote against — so the builder gets hardcoded
numbers to look right, and those hardcoded numbers survive into the demo because
nobody goes back to remove them. Two weeks later Dev B builds the real matrix and the
two halves disagree about what a tarpaulin costs.

The fix is not "wait for the other developer". It is to build the shared spine first,
together, in one sitting, and then split.

## The spine — build this together, in this order

1. **Migrations + models** for the whole schema, both halves. Nobody starts a screen
   until this is merged. It is one commit and it unblocks everything.
2. **`PriceCalculator`** — one service class, one public method, documented in
   `CONTRACT.md`. Both halves call this. Neither reimplements it.
3. **Seeders** — seven fake shops with real-looking matrices, a handful of customers,
   a few jobs in different states. This is what lets Dev A build the entire customer
   side before the shop panel exists.

Point 3 matters more than it looks. With seeders, the customer side is fully
buildable and fully demoable on day one, and Dev B can build the shop panel against
data that already exists instead of inventing it. It also gives you something to show
if a panel asks for progress in week one.

## Then split, in this order

**Dev A — landing + customer**

| # | Task | Depends on |
|---|---|---|
| 1 | Blade layout, brand tokens, marketing sections | spine |
| 2 | Shop directory + filters + map | seeders |
| 3 | Shop profile page | directory |
| 4 | Order builder (Vue) — calls `PriceCalculator` | contract |
| 5 | Checkout (Vue) + PayMongo sandbox intent | builder |
| 6 | Job status + proof approval (Vue) | checkout |
| 7 | My jobs + profile | status |

**Dev B — shop panel + admin**

| # | Task | Depends on |
|---|---|---|
| 1 | Filament install, two panels, one guard | spine |
| 2 | Price matrix resource (feeds Dev A's builder) | spine |
| 3 | Jobs resource + status transitions | matrix |
| 4 | Production board (custom Filament page) | jobs |
| 5 | Shop reports + payouts | jobs |
| 6 | Admin dashboard + shop approvals | matrix |
| 7 | Users, roles, policies (4 roles) | admin |
| 8 | CMS — site content resource | spine |

Dev B's task 2 is the highest priority in the whole backlog, because Dev A's task 4
is blocked on real matrix data. If you only get one thing from the other developer in
week one, get the price matrix.

## Rules that keep you out of each other's way

- **One migration per PR, never edit a merged migration.** If you need a column, add
  a new migration. Editing a merged one breaks the other developer's database.
- **Never compute money in the browser.** Vue may preview a price; the server
  recalculates on submit. All money is stored as integer centavos, never floats.
- **Never mount a Vue component inside a Filament page.** Livewire and Vue both want
  the DOM. Vue lives in `resources/js/`, Filament lives in `app/Filament/`.
- **Branch per issue**, PR into `main`, one reviewer. `main` should always run.
- **Seeders are shared.** If you change the schema, update the seeder in the same PR,
  or the other developer's `migrate:fresh --seed` breaks.

## How a change lands

`main` is protected by a **ruleset**, not by good intentions. Direct pushes are
refused; the only way in is a pull request that passes the checks.

```
issue → assign yourself → branch → commit → push branch → open PR → review → squash merge
```

1. **Claim the issue on GitHub first.** `/assign` in a comment. An issue with no
   assignee is fair game; an issue with one is not. This is the only thing that
   prevents two people building the same feature — a ruleset cannot see intent.
2. **Branch off `main`**, named after the issue: `feature/7-reports-payouts`.
3. **Push the branch** (`git push origin feature/7-reports-payouts`). Branches are
   always pushable; only `main` is locked.
4. **Open the PR.** The template asks what it closes — write `Closes #7`. GitHub
   links the PR to the issue and closes it when the PR merges.
5. **The checks must be green:** `Tests` (the whole suite on a fresh clone) and
   `Issue link` (refuses a PR with no issue number). Both are required.
6. **One approval.** The other developer reviews. A new push dismisses the old
   approval, so review the final state, not an earlier one.
7. **Squash merge.** Merge commits and rebase are disabled at the ruleset level, so
   `main` stays linear: one commit per issue, and `git log` reads like the backlog.

If you push to `main` by mistake, Git answers:

```
remote: error: GH013: Repository rule violations found for refs/heads/main.
remote: - Changes must be made through a pull request.
```

Nothing is lost — `git switch -c feature/whatever` and push the branch instead.

## Definition of done, per half

**Dev A is done when** a stranger can open the site, find a shop, build a tarpaulin
job, pay in sandbox, and approve a proof — all against seeded data, with no
hardcoded prices anywhere in the Vue components.

**Dev B is done when** a shop owner can log in, change a price tier, see that change
reflected on the storefront without a deploy, move a job across the production board,
and an admin can approve a new shop and edit the homepage hero copy.
