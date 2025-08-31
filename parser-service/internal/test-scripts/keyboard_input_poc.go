package main

import (
	"flag"
	"fmt"
	"os"
	"strconv"

	demoinfocs "github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs"
	common "github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	events "github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
)

// Button constants from CS2
const (
	IN_ATTACK    = uint64(0x1)
	IN_JUMP      = uint64(0x2)
	IN_DUCK      = uint64(0x4)
	IN_FORWARD   = uint64(0x8)
	IN_BACK      = uint64(0x10)
	IN_USE       = uint64(0x20)
	IN_CANCEL    = uint64(0x40)
	IN_LEFT      = uint64(0x80)
	IN_RIGHT     = uint64(0x100)
	IN_MOVELEFT  = uint64(0x200)
	IN_MOVERIGHT = uint64(0x400)
	IN_ATTACK2   = uint64(0x800)
	IN_RUN       = uint64(0x1000)
	IN_RELOAD    = uint64(0x2000)
	IN_ALT1      = uint64(0x4000)
	IN_ALT2      = uint64(0x8000)
	IN_SCORE     = uint64(0x10000)
	IN_SPEED     = uint64(0x20000)
	IN_WALK      = uint64(0x40000)
	IN_ZOOM      = uint64(0x80000)
	IN_WEAPON1   = uint64(0x100000)
	IN_WEAPON2   = uint64(0x200000)
	IN_BULLRUSH  = uint64(0x400000)
	IN_GRENADE1  = uint64(0x800000)
	IN_GRENADE2  = uint64(0x1000000)
	IN_LOOKSPIN  = uint64(0x2000000)
)

type KeyboardState struct {
	Player        *common.Player
	LastButtons   uint64
	TickTimestamp int
}

// convertButtonsToKeyString converts button bitmask to human-readable string
func convertButtonsToKeyString(buttons uint64) string {
	var keys []string

	if buttons&IN_FORWARD != 0 {
		keys = append(keys, "Forward")
	}
	if buttons&IN_BACK != 0 {
		keys = append(keys, "Back")
	}
	if buttons&IN_MOVELEFT != 0 {
		keys = append(keys, "Left")
	}
	if buttons&IN_MOVERIGHT != 0 {
		keys = append(keys, "Right")
	}
	if buttons&IN_JUMP != 0 {
		keys = append(keys, "Jump")
	}
	if buttons&IN_DUCK != 0 {
		keys = append(keys, "Crouch")
	}
	if buttons&IN_WALK != 0 {
		keys = append(keys, "Walk")
	}
	if buttons&IN_ATTACK != 0 {
		keys = append(keys, "Attack")
	}
	if buttons&IN_ATTACK2 != 0 {
		keys = append(keys, "Attack2")
	}

	if len(keys) == 0 {
		return "None"
	}

	result := keys[0]
	for i := 1; i < len(keys); i++ {
		result += " + " + keys[i]
	}
	return result
}

// formatRoundTime converts seconds to MM:SS format
func formatRoundTime(seconds int) string {
	minutes := seconds / 60
	secs := seconds % 60
	return fmt.Sprintf("%02d:%02d", minutes, secs)
}

// getRoundTimeFromTick calculates round time from current tick and round start
func getRoundTimeFromTick(currentTick, roundStartTick int) int {
	// CS2 runs at 64 ticks per second
	ticksSinceRoundStart := currentTick - roundStartTick
	if ticksSinceRoundStart < 0 {
		return 0
	}
	return ticksSinceRoundStart / 64
}

func main() {
	demo := flag.String("demo", "", "Demo file path")
	steamID := flag.String("steamid", "", "Player Steam ID to track")
	output := flag.String("output", "", "Output file path (default: stdout)")

	flag.Parse()

	if *demo == "" || *steamID == "" {
		fmt.Println("Usage: go run keyboard_input_poc.go -demo=<path> -steamid=<steamid> [-output=<file>]")
		fmt.Println("Example: go run keyboard_input_poc.go -demo=match.dem -steamid=76561198123456789")
		os.Exit(1)
	}

	// Convert string steamID to uint64
	playerSteamID, err := strconv.ParseUint(*steamID, 10, 64)
	if err != nil {
		fmt.Printf("Invalid Steam ID: %v\n", err)
		os.Exit(1)
	}

	fmt.Printf("Keyboard Input Tracker - Proof of Concept\n")
	fmt.Printf("Demo: %s\n", *demo)
	fmt.Printf("Player Steam ID: %d\n", playerSteamID)
	fmt.Println("----")

	// Open demo file
	f, err := os.Open(*demo)
	if err != nil {
		fmt.Printf("Error opening demo file: %v\n", err)
		os.Exit(1)
	}
	defer f.Close()

	// Create parser
	p := demoinfocs.NewParser(f)
	defer p.Close()

	// Set up output
	var outputFile *os.File
	if *output != "" {
		outputFile, err = os.Create(*output)
		if err != nil {
			fmt.Printf("Error creating output file: %v\n", err)
			os.Exit(1)
		}
		defer outputFile.Close()
		fmt.Fprintf(outputFile, "Round,RoundTime,Tick,Player,Keys\n") // CSV header
	}

	// Track keyboard states
	keyboardStates := make(map[uint64]*KeyboardState)
	roundStarts := make([]int, 0) // Track all round start ticks
	currentRound := 0

	// Track round starts
	p.RegisterEventHandler(func(e events.RoundStart) {
		roundStartTick := p.GameState().IngameTick()
		roundStarts = append(roundStarts, roundStartTick)
		currentRound++
		fmt.Printf("Round %d started at tick %d\n", currentRound, roundStartTick)
	})

	// Track keyboard inputs using FrameDone event (more reliable in v5)
	p.RegisterEventHandler(func(e events.FrameDone) {
		currentTick := p.GameState().IngameTick()

		// Get all participants and check our target player
		participants := p.GameState().Participants().All()
		for _, player := range participants {
			if player.SteamID64 != playerSteamID {
				continue
			}

			// Try to access the player pawn entity directly
			pawnEntity := player.PlayerPawnEntity()
			if pawnEntity == nil {
				continue
			}

			// Check if this is the right entity type
			if pawnEntity.ServerClass().Name() != "CCSPlayerPawn" {
				continue
			}

			// Try to get the button property directly
			buttonProp, hasButtonProp := pawnEntity.PropertyValue("m_pMovementServices.m_nButtonDownMaskPrev")
			if !hasButtonProp {
				continue
			}

			newButtons := buttonProp.UInt64()

			// Get keyboard state
			if keyboardStates[player.SteamID64] == nil {
				keyboardStates[player.SteamID64] = &KeyboardState{
					Player: player,
				}
			}

			state := keyboardStates[player.SteamID64]

			// Only log if buttons changed (to avoid spam)
			if state.LastButtons != newButtons {
				// Find the most recent round start that's <= current tick and which round number
				var currentRoundStart int = 0
				var roundNumber int = 0
				for i, roundStart := range roundStarts {
					if roundStart <= currentTick {
						currentRoundStart = roundStart
						roundNumber = i + 1 // Round numbers start from 1
					} else {
						break // Round starts are in order, so we can break here
					}
				}

				roundTime := 0
				if currentRoundStart > 0 {
					roundTime = getRoundTimeFromTick(currentTick, currentRoundStart)
				}

				keyString := convertButtonsToKeyString(newButtons)

				logLine := fmt.Sprintf("[Round: %d | RoundTime: %s | Tick: %d] Player: %s | Keys: %s",
					roundNumber,
					formatRoundTime(roundTime),
					currentTick,
					player.Name,
					keyString,
				)

				if outputFile != nil {
					// CSV format
					fmt.Fprintf(outputFile, "%d,%s,%d,%s,%s\n",
						roundNumber,
						formatRoundTime(roundTime),
						currentTick,
						player.Name,
						keyString,
					)
				} else {
					fmt.Println(logLine)
				}

				state.LastButtons = newButtons
				state.TickTimestamp = currentTick
			}
		}
	})

	// Parse the demo
	err = p.ParseToEnd()
	if err != nil {
		fmt.Printf("Error parsing demo: %v\n", err)
		os.Exit(1)
	}

	fmt.Printf("\nParsing completed successfully!\n")
	if *output != "" {
		fmt.Printf("Results written to: %s\n", *output)
	}
}
