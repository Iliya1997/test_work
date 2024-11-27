<?php

namespace App\Console\Commands;

use App\Services\CsvService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class csvWork extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'csv:parse
    {--file=*}
    {--test=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (empty($this->options()['file'])) {
            $this->error('Please set file option --file');
        }

        $file_name = $this->options()['file'][0];

        try {
            $csv_service = new CsvService($file_name, !empty($this->options()['test']) && $this->options()['test'][0] === 'true');

            $csv_service->readCsv();

            $statistics         = $csv_service->getStatistics();
            $total_count        = $statistics['total_count'];
            $failed_inserted    = $statistics['failed_inserted'];
            $updated_rows       = $statistics['updated_rows'];
            $csv_report         = $statistics['csv_report'];

            echo "Total rows were successfully processed: $total_count" . PHP_EOL;
            echo "Total Updated rows: " . count($updated_rows) . PHP_EOL;

            if (count($updated_rows) > 0) {
                echo str_repeat('*', 80) . PHP_EOL;
            }

            foreach ($updated_rows as $updated_row) {
                echo "Updated product code: $updated_row" . PHP_EOL;
            }

            echo "\nTotal Failed inserted count: " . count($failed_inserted) . '. Check on correctly fill fields' . PHP_EOL;
            echo str_repeat('*', 80) . PHP_EOL;

            foreach ($failed_inserted as $product_code) {
                echo "Failed inserted product code: $product_code" . PHP_EOL;
            }

            echo "\nCsv Report: " . PHP_EOL;
            echo str_repeat('*', 80) . PHP_EOL;

            echo "$csv_report\n";
        } catch (\Exception $e) {
            Log::channel('csvlog')->info($e->getMessage());
            $this->error($e->getMessage());
        }
    }
}
