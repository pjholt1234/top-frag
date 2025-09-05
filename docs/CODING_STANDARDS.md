# Coding Standards

## 🎯 **Purpose**
This document defines the coding standards and conventions for the CS:GO demo parsing platform.

## 🚨 **CRITICAL: FOLLOW THESE STANDARDS EXACTLY**

**These standards are MANDATORY and must be followed for all code changes.**

---

## 📋 **General Principles**

### **1. Self-Documenting Code**
- **Minimal comments**: Code should be self-documenting through clear naming
- **Comments for non-obvious code**: Only add comments when the code's purpose isn't clear
- **Descriptive names**: Use clear, descriptive variable and function names
- **Intent-revealing**: Code should clearly express its intent
- **Avoid abbreviations**: Use full words instead of abbreviations

### **2. Minimal Indentation**
- **Flat structures**: Use flat structures where possible
- **Early returns**: Use early returns for error cases and validation
- **Happy path last**: The main success path should be the final return
- **Guard clauses**: Use guard clauses to handle edge cases
- **Avoid deep nesting**: Keep nesting levels to a minimum

### **3. Consistency**
- **Follow existing patterns**: Maintain consistency with existing codebase
- **Use established conventions**: Follow language-specific conventions
- **Consistent naming**: Use consistent naming patterns throughout

---

## 🔧 **Go (Parser Service & Parser Test)**

### **Code Style**
- **Minimal comments**: Only add comments when code purpose isn't obvious
- **Minimal indentation**: Use flat structures where possible
- **Early returns**: Use early returns for error cases and validation
- **Happy path last**: The main success path should be the final return
- **Guard clauses**: Use guard clauses for error handling

### **Naming Conventions**
- **Functions**: `camelCase` for private, `PascalCase` for public
- **Variables**: `camelCase`
- **Constants**: `PascalCase` or `UPPER_CASE`
- **Types**: `PascalCase`

### **Error Handling**
- **Explicit error handling**: Always handle errors explicitly
- **Error wrapping**: Use `fmt.Errorf` with `%w` verb for error wrapping
- **Context**: Use context for cancellation and timeouts

### **Testing**
- **Test files**: `*_test.go` files
- **Test functions**: `TestFunctionName` or `TestStruct_Method`
- **Table-driven tests**: Use table-driven tests for multiple scenarios
- **Test coverage**: Aim for high test coverage

### **Example**
```go
func ProcessGunfightEvent(ctx context.Context, event GunfightEvent) error {
    if event.Player1SteamID == "" {
        return fmt.Errorf("player1 steam ID is required")
    }
    
    if event.Player2SteamID == "" {
        return fmt.Errorf("player2 steam ID is required")
    }
    
    // Happy path - main success logic at the end
    return h.processEvent(ctx, event)
}
```

---

## 🐘 **PHP (Laravel Web App)**

### **Code Style**
- **Minimal comments**: Only add comments when code purpose isn't obvious
- **Minimal indentation**: Use flat structures where possible
- **Early returns**: Use early returns for error cases and validation
- **Happy path last**: The main success path should be the final return
- **Guard clauses**: Use guard clauses for validation

### **Laravel Conventions**
- **Models**: Use Eloquent models with proper relationships
- **Controllers**: Keep controllers thin, delegate to services
- **Request Classes**: Use Form Request classes for validation in controllers
- **Services**: Use service classes for business logic
- **Resources**: Use API resources for response formatting

### **Request Class Pattern**
- **Validation**: All validation logic should be in Form Request classes
- **Controllers**: Controllers should use Request classes, not manual validation
- **Naming**: Request classes should be named `{Action}{Model}Request` (e.g., `StoreGunfightEventRequest`)
- **Authorization**: Use Request classes for authorization logic when needed
- **Clean controllers**: Controllers should focus on orchestration, not validation

### **Naming Conventions**
- **Classes**: `PascalCase`
- **Methods**: `camelCase`
- **Variables**: `camelCase`
- **Constants**: `UPPER_CASE`

### **Testing**
- **Feature tests**: Test API endpoints and user interactions
- **Unit tests**: Test individual classes and methods
- **Database tests**: Use RefreshDatabase trait
- **Test coverage**: Aim for high test coverage

### **Controller Example**
```php
class GunfightEventController extends Controller
{
    public function store(StoreGunfightEventRequest $request, GunfightEventService $service)
    {
        // Request class handles validation automatically
        $validatedData = $request->validated();
        
        // Happy path - main success logic at the end
        $service->processGunfightEvent($validatedData);
        
        return response()->json(['success' => true]);
    }
}
```

### **Service Example**
```php
class GunfightEventService
{
    public function processGunfightEvent(array $data): void
    {
        // Data is already validated by Request class
        // Happy path - main success logic at the end
        $this->storeEvent($data);
    }
}
```

---

## ⚛️ **TypeScript/React (Frontend)**

### **Code Style**
- **Minimal comments**: Only add comments when code purpose isn't obvious
- **Minimal indentation**: Use flat structures where possible
- **Early returns**: Use early returns for error cases and validation
- **Happy path last**: The main success path should be the final return
- **Guard clauses**: Use guard clauses for validation

### **React Conventions**
- **Functional components**: Use functional components with hooks
- **Higher Order Components**: Use HOCs for cross-cutting concerns and code reuse
- **Custom hooks**: Extract reusable logic into custom hooks
- **Type safety**: Use proper TypeScript types
- **Component composition**: Use component composition over inheritance

### **Higher Order Component Pattern**
- **Cross-cutting concerns**: Use HOCs for authentication, loading states, error handling
- **Code reuse**: Extract common logic into reusable HOCs
- **Naming**: HOCs should be named with `with` prefix (e.g., `withAuth`, `withLoading`)
- **Composition**: HOCs can be composed together for multiple concerns
- **Type safety**: Use proper TypeScript generics for HOC props

### **Naming Conventions**
- **Components**: `PascalCase`
- **Functions**: `camelCase`
- **Variables**: `camelCase`
- **Constants**: `UPPER_CASE`

### **HOC Example**
```typescript
// Higher Order Component for authentication
function withAuth<P extends object>(Component: React.ComponentType<P>) {
  return function AuthenticatedComponent(props: P) {
    const { isAuthenticated } = useAuth();
    
    if (!isAuthenticated) {
      return <LoginPrompt />;
    }
    
    // Happy path - main success logic at the end
    return <Component {...props} />;
  };
}

// Higher Order Component for loading states
function withLoading<P extends object>(Component: React.ComponentType<P>) {
  return function LoadingComponent(props: P & { isLoading?: boolean }) {
    if (props.isLoading) {
      return <LoadingSpinner />;
    }
    
    // Happy path - main success logic at the end
    return <Component {...props} />;
  };
}

// Usage with composition
const AuthenticatedGunfightEvent = withAuth(withLoading(GunfightEventComponent));
```

### **Component Example**
```typescript
interface GunfightEventProps {
  event: GunfightEvent;
  onSelect: (event: GunfightEvent) => void;
}

export function GunfightEventComponent({ event, onSelect }: GunfightEventProps) {
  if (!event.player1SteamId) {
    return null;
  }
  
  if (!event.player2SteamId) {
    return null;
  }
  
  // Happy path - main success logic at the end
  return (
    <div onClick={() => onSelect(event)}>
      {/* Component content */}
    </div>
  );
}
```

---

## 🧪 **Testing Standards**

### **Go Testing**
- **Test files**: `*_test.go` files
- **Test functions**: `TestFunctionName` or `TestStruct_Method`
- **Table-driven tests**: Use table-driven tests for multiple scenarios
- **Test coverage**: Aim for high test coverage

### **PHP Testing**
- **Feature tests**: Test API endpoints and user interactions
- **Unit tests**: Test individual classes and methods
- **Database tests**: Use RefreshDatabase trait
- **Test coverage**: Aim for high test coverage

### **Testing Requirements**
- **All new code**: Must have corresponding tests
- **Bug fixes**: Must include regression tests
- **API changes**: Must include integration tests
- **Database changes**: Must include database tests

---

## 🔧 **Code Quality Tools**

### **Go**
- **Linter**: `golangci-lint run`
- **Formatter**: `gofmt -w .`
- **Vet**: `go vet ./...`

### **PHP**
- **Linter**: `./vendor/bin/pint --test`
- **Formatter**: `./vendor/bin/pint`
- **Static Analysis**: `./vendor/bin/phpstan`

### **TypeScript**
- **Linter**: `npm run lint`
- **Formatter**: `npm run format`
- **Type Check**: `npm run type-check`

---

## 🚨 **CRITICAL REMINDER**

**CODING STANDARDS ARE MANDATORY:**
- Follow these standards EXACTLY as written
- Do NOT deviate from established patterns
- Only add comments when code purpose isn't obvious
- Do NOT use deep indentation
- Maintain consistency with existing codebase

**VIOLATING THESE STANDARDS CONSTITUTES TASK FAILURE**

---

**Remember**: Code should be self-documenting, consistent, and follow established patterns.
