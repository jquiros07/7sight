# CLAUDE.md — 7Sight

## Working style & permissions

You may **read** any file in this project freely — no need to ask. Reading,
listing directories, and searching are always fine.

Before taking any **modifying or executing** action, **stop and ask for my
explicit permission first**. Specifically, ask before you:
- Create a new file or directory
- Edit, rename, move, or delete an existing file
- Execute a file, script, or program (build tools, package managers,
  migrations, or any shell command that changes state)

When you ask, briefly tell me: what you want to do, which file(s)/command(s) it
affects, and why. Wait for my "yes" before proceeding. When in doubt about
whether something counts as a modifying action, treat it as one and ask.

<!-- add your next permission rule here -->

## Implementation guidelines

### Simplicity first
- Keep It Simple. Favor the most straightforward solution that works. Do not add
  complexity, abstraction, or indirection the task doesn't clearly require.
- Code must be readable and easy to understand. Prefer clear names and obvious
  flow over cleverness.

### Conventions & patterns
- Follow standard Laravel conventions (naming, structure, Eloquent, form
  requests, resources, etc.).
- Before applying a specific or opinionated pattern (a new design pattern,
  package, or architectural choice), **ask me first** — explain the pattern, why
  it fits, and the simpler alternative. (Everyday convention-following doesn't
  need a prompt; this is for opinionated/architectural decisions.)
- Use the **Action + Controller** pattern: controllers stay thin and handle only
  the HTTP layer (validate, call an Action, return a response); business logic
  lives in single-purpose Action classes.
- Always check linting, verify correct build and run a quick test of the implementation before considering the task done.

### Error handling
- Wrap logic that can fail in `try/catch`.
- In the `catch`, log the exception before handling or rethrowing
  (e.g. `Log::error($e->getMessage(), ['exception' => $e]);`). Never swallow an
  exception silently.

### Fixing bugs / making changes
When updating or fixing an issue:
- The fix must actually resolve the error.
- Keep changes **simple, minimal, and non-destructive**.
- Do not alter or refactor code that already works, and do not change existing
  behavior beyond what's needed to fix the specific problem.
- Touch the smallest surface area that solves it.

### Proactive suggestions (always welcome)
- Suggestions for improvement are welcome — offer them separately from the
  requested change, and let me decide.
- Suggestions for a simple, useful, functional **unit test** covering the change
  are welcome — keep proposed tests small and focused on real behavior.
