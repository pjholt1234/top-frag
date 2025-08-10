<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ParserServiceConnector;
use App\Jobs\ParseDemo;

class TestParserFlowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:parser {--file=ancient-mm.dem : Demo file to upload}';

    protected $description = 'Test the complete parser flow by uploading a demo file to the parser service';


    public function __construct(private readonly ParserServiceConnector $parserServiceConnector)
    {
        parent::__construct();
    }

    public function handle()
    {
        $fileName = $this->option('file');
        $filePath = storage_path("app/public/{$fileName}");

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $fileSize = filesize($filePath);
        $this->info("File size: " . number_format($fileSize / 1024 / 1024, 2) . " MB");

        // Dispatch the ParseDemo job with the file path
        ParseDemo::dispatch($filePath);

        $this->info("ParseDemo job dispatched successfully!");
    }
}
