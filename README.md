# top-frag

## Development Setup

### Pre-commit Hooks

This project uses pre-commit hooks to automatically format and lint TypeScript files before each commit. The hooks are configured in the root `package.json` and will:

- Automatically format TypeScript/React files with Prettier
- Automatically fix ESLint issues where possible
- Run on every commit to ensure code quality

To set up the pre-commit hooks:

```bash
npm install
```

The hooks will be automatically installed and will run on every commit.