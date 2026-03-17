Build: $ARGUMENTS

Rules from CLAUDE.md apply. Steps:
1. Read CLAUDE.md first
2. Check if context file exists: .claude/context/$ARGUMENTS.md — if yes, read it
3. Write the class
4. Write tests in tests/ mirroring src/ path
5. Run: composer test — fix all failures
6. Run: composer lint — fix all phpcs violations
7. Write docs/{module}.md (3-5 sentences, usage example, public API list)
8. Report: "Done. Tests: X passed. Lint: clean."
