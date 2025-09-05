# Task Execution Guide

## 🚨 **CRITICAL: FOLLOW THIS GUIDE EXACTLY**

**This guide is MANDATORY and must be followed for every task unless the user explicitly says to ignore a particular part.**

**If the user asks you to ignore any part of this guide, you MUST ask them to confirm before proceeding.**

**VIOLATING THIS PROCESS CONSTITUTES TASK FAILURE**

---

## 📋 **11-Step Task Execution Process**

### **Step 1: 📖 Read the READMEs**
- **Parser Service**: `parser-service/README.md` - Architecture, API, data structures
- **Web App**: `web-app/README.md` - Laravel/React setup, linting, formatting  
- **Root**: `README.md` - Pre-commit hooks, development setup
- **Documentation**: `docs/` folder for specific implementation details

### **Step 2: ❓ Query Any Points You Don't Understand**
- Use semantic search to understand unfamiliar concepts
- Ask clarifying questions before proceeding
- Reference specific files when seeking clarification
- Don't make assumptions about project structure or requirements

### **Step 3: 🚧 Respect Project Boundaries**
**These boundaries are SACRED and NON-NEGOTIABLE:**

- **Database**: NEVER modify database schema without explicit permission
- **Configuration**: NEVER change production configurations without explicit permission  
- **Dependencies**: NEVER add new dependencies without explicit permission

**Additional boundaries set by the user in their prompt must also be respected.**

**If you need to violate any boundary, you MUST ask for explicit permission first.**

### **Step 4: 🎨 Follow Coding Standards**
- Reference `docs/CODING_STANDARDS.md` for detailed coding guidelines
- Follow project-specific patterns and conventions
- Maintain consistency with existing codebase

### **Step 5: 🧪 Testing Requirements**
**All PHP and Go code must be tested:**

- **Go**: Create unit tests for new functions, run `go test`
- **PHP**: Create feature tests for new functionality, run `php artisan test`
- **If existing logic has changed**: Confirm with user what to do
- **If tests fail**: Fix issues or ask user for guidance

### **Step 6: ✅ Ask for Sign Off**
- If you think everything is working and tests have passed
- Ask the user to confirm before proceeding to linting/formatting
- Wait for explicit user approval

### **Step 7: 🔧 Run Linting Checks**
- **Go**: `golangci-lint run`
- **PHP**: `./vendor/bin/pint --test`
- **TypeScript**: `npm run lint`

### **Step 8: 🎨 Run Formatters**
- **Go**: `gofmt -w .`
- **PHP**: `./vendor/bin/pint`
- **TypeScript**: `npm run format`

### **Step 9: 📊 Summarize Changes**
Provide comprehensive summary including:
- What was changed and why
- Testing performed and results
- Linting and formatting results
- Any documentation updates made
- Potential impacts or considerations

### **Step 10: 🔍 Final Verification**
- Confirm all tests still pass after formatting
- Verify no breaking changes introduced
- Ensure all boundaries were respected

### **Step 11: ✅ Task Completion**
- Mark task as complete
- Update any relevant documentation
- Report final status to user

---

## 🚨 **Emergency Stop Conditions**

**CRITICAL: Stop and ask if you encounter:**
- Unclear requirements or specifications
- Conflicting information in documentation
- Need to violate any project boundaries
- Existing logic changes that might break things
- Dependencies that might conflict
- Performance implications you're unsure about
- Security concerns or potential vulnerabilities

## ⚠️ **Code Quality Warnings**

**These are NOT show-stoppers, but should be flagged to the user:**

### **1. Poor Separation of Concerns**
- **Flag when**: Code mixes multiple responsibilities in one function/class
- **Example**: Controller handling validation, business logic, and data formatting
- **Action**: Warn user and suggest refactoring, but continue if they say it's okay

### **2. File Length**
- **Flag when**: Files exceed reasonable length (e.g., >300 lines for functions, >500 lines for files)
- **Action**: Warn user about maintainability, but continue if they say it's okay

### **3. Additional Warnings to Consider**
- **Deep nesting**: Functions with >4 levels of indentation
- **Large functions**: Functions with >50 lines
- **Complex conditionals**: Multiple nested if/else statements
- **Duplicate code**: Repeated logic that could be extracted

**Process**: Flag these issues, explain the concern, ask if user wants to address them, but continue with the task if they say it doesn't matter.

---

## ⚠️ **ABSOLUTE BOUNDARIES - DO NOT VIOLATE**

**These boundaries are SACRED and NON-NEGOTIABLE:**
- **Database**: NEVER modify database schema without explicit permission
- **Configuration**: NEVER change production configurations without explicit permission
- **Dependencies**: NEVER add new dependencies without explicit permission
- **User-specified boundaries**: Any additional boundaries set in the user's prompt

**VIOLATION OF THESE BOUNDARIES CONSTITUTES TASK FAILURE**

---

## 📝 **Example Task Execution**

```
Task: "Add weapon accuracy field to gunfight events"

1. ✅ Read parser-service/README.md - understand gunfight event structure
2. ✅ Read docs/parser-service-data-types.md - understand current fields
3. ✅ Query: "What is the current gunfight event processing flow?"
4. ✅ Respect boundaries: Don't modify database schema
5. ✅ Follow coding standards: Reference CODING_STANDARDS.md
6. ✅ Create test for new field in gunfight_handler_test.go
7. ✅ Run: go test ./internal/parser/
8. ✅ Ask user: "Tests pass, ready for linting/formatting. Proceed?"
9. ✅ Run: golangci-lint run
10. ✅ Run: gofmt -w .
11. ✅ Summarize: "Added weapon_accuracy field, tests pass, code formatted"
```

---

## 🎯 **Success Criteria**

A task is complete when:
- ✅ All requirements are met
- ✅ Code follows project standards
- ✅ Tests are written and passing
- ✅ Linting and formatting pass
- ✅ Documentation is updated if needed
- ✅ No breaking changes introduced
- ✅ All questions have been answered
- ✅ **NO BOUNDARIES WERE VIOLATED**
- ✅ **User has given final approval**

---

## 🚨 **CRITICAL REMINDER**

**PROCESS DISCIPLINE IS MANDATORY:**
- Follow the 11-step checklist EXACTLY as written
- Do NOT add "helpful" extras beyond scope
- Do NOT make assumptions about what "would be better"
- Do NOT touch things that weren't explicitly requested
- When in doubt, ASK - don't assume
- **WAIT for user approval before proceeding to each major step**

**VIOLATING THESE RULES CONSTITUTES TASK FAILURE**

---

**Remember**: When in doubt, ask! It's better to clarify than to make incorrect assumptions.