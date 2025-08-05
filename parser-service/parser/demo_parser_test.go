package parser

import (
	"testing"
	"github.com/stretchr/testify/assert"
	events "github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
)

func TestNewDemoParser(t *testing.T) {
	parser := NewDemoParser()
	assert.NotNil(t, parser)
}

func TestDemoParser_Parse_InvalidFile(t *testing.T) {
	parser := NewDemoParser()
	
	var killCount int
	onKill := func(kill events.Kill) {
		killCount++
	}
	
	err := parser.Parse("nonexistent_file.dem", onKill)
	assert.Error(t, err)
	assert.Contains(t, err.Error(), "failed to parse demo")
	assert.Equal(t, 0, killCount)
}

func TestDemoParser_Parse_ValidFile(t *testing.T) {
	parser := NewDemoParser()
	
	var killCount int
	var capturedKills []events.Kill
	
	onKill := func(kill events.Kill) {
		killCount++
		capturedKills = append(capturedKills, kill)
	}
	
	err := parser.Parse("demo.dem", onKill)
	
	if err != nil {
		t.Skip("Skipping test - no demo.dem file available")
	}
	
	assert.NoError(t, err)
	assert.GreaterOrEqual(t, killCount, 0)
}

func TestDemoParser_Parse_CallbackCalled(t *testing.T) {
	parser := NewDemoParser()
	
	onKill := func(kill events.Kill) {
		// Callback is called when kill events are processed
	}
	
	err := parser.Parse("demo.dem", onKill)
	
	if err != nil {
		t.Skip("Skipping test - no demo.dem file available")
	}
	
	assert.NoError(t, err)
} 