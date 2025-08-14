<?php

namespace App\Console\Commands;

use App\Models\GrenadeEvent;
use Illuminate\Console\Command;

class FindSmokeGrenadesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grenades:find {steam_id : The Steam ID to search for} {--output=console : Output format (console, file)} {--file=smoke_positions.txt : Output file name when using file output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find all smoke grenades for a given Steam ID and generate position strings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $steamId = $this->argument('steam_id');
        $outputFormat = $this->option('output');
        $outputFile = $this->option('file');

        $this->info("Searching for smoke grenades for Steam ID: {$steamId}");

        // Find all smoke grenade events for the given Steam ID
        $smokeGrenades = GrenadeEvent::where('player_steam_id', $steamId)
            ->orderBy('match_id')
            ->orderBy('round_number')
            ->orderBy('round_time')
            ->get();

        if ($smokeGrenades->isEmpty()) {
            $this->warn("No smoke grenades found for Steam ID: {$steamId}");

            return 1;
        }

        $this->info("Found {$smokeGrenades->count()} grenade(s)");

        // Generate position strings
        $positionStrings = [];
        foreach ($smokeGrenades as $grenade) {
            $positionString = $grenade->generatePositionString();
            $positionStrings[] = $positionString;
        }

        // Output based on format
        if ($outputFormat === 'file') {
            $this->outputToFile($positionStrings, $outputFile);
        } else {
            $this->outputToConsole($positionStrings, $smokeGrenades);
        }

        $this->info('Command completed successfully!');

        return 0;
    }

    /**
     * Output position strings to console with additional details
     */
    private function outputToConsole(array $positionStrings, $smokeGrenades): void
    {
        $this->newLine();
        $this->info('Position strings:');
        $this->newLine();

        foreach ($positionStrings as $index => $positionString) {
            $grenade = $smokeGrenades[$index];
            $this->line("Match: {$grenade->match_id}, Round: {$grenade->round_number}, Time: {$grenade->round_time}s");
            $this->line($positionString);
            $this->line("Grenade Type: {$grenade->grenade_type->name}");
            $this->newLine();
        }
    }

    /**
     * Output position strings to file
     */
    private function outputToFile(array $positionStrings, string $filename): void
    {
        $filePath = storage_path("app/{$filename}");

        $content = implode("\n", $positionStrings);

        if (file_put_contents($filePath, $content) === false) {
            $this->error("Failed to write to file: {$filePath}");

            return;
        }

        $this->info("Position strings written to: {$filePath}");
        $this->info('Total lines written: '.count($positionStrings));
    }
}
