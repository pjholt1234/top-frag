package main

import (
	"log"
	"path/filepath"
	"app/parser"
)

func main() {
	absPath, err := filepath.Abs("demo.dem")
	if err != nil {
		log.Panic("failed to get absolute path: ", err)
	}
	log.Printf("Demo file absolute path: %s", absPath)

	demoParser := parser.NewDemoParser()
	matchAnalyser := parser.NewMatchAnalyser()
	
	err = demoParser.Parse(absPath, matchAnalyser.ProcessKillEvent)
	if err != nil {
		log.Panic("failed to parse demo: ", err)
	}

	players := matchAnalyser.GetPlayers()
	log.Printf("Total kills: %d", matchAnalyser.GetTotalKills())
	
	for steamID, player := range players {
		log.Printf("SteamID %s: %+v", steamID, player)
		log.Printf("  K/D Ratio: %.2f", player.KDRatio())
		log.Printf("  Total Score: %d", player.TotalScore())
	}
}