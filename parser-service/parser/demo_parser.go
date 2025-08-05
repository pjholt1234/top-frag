package parser

import (
	"fmt"
	demoinfocs "github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs"
	events "github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
)

type DemoParser struct{}

func NewDemoParser() *DemoParser {
	return &DemoParser{}
}

type KillEventHandler func(kill events.Kill)

func (p *DemoParser) Parse(demoPath string, onKill KillEventHandler) error {
	err := demoinfocs.ParseFile(demoPath, func(parser demoinfocs.Parser) error {
		parser.RegisterEventHandler(func(kill events.Kill) {
			onKill(kill)
		})
		return nil
	})
	
	if err != nil {
		return fmt.Errorf("failed to parse demo: %w", err)
	}
	
	return nil
} 