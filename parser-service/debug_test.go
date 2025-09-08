package main

import (
	"testing"
)

func TestDebugSetup(t *testing.T) {
	// This is a simple test to verify debugging works
	message := "Hello, Debugger!"

	// Set a breakpoint on the next line to test debugging
	if message != "Hello, Debugger!" {
		t.Errorf("Expected 'Hello, Debugger!', got %s", message)
	}

	t.Logf("Debug test passed: %s", message)
}
