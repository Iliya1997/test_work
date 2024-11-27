<?php

namespace App\Services;

use App\Exceptions\CsvException;
use App\Models\ProductData;
use Illuminate\Support\Facades\DB;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\SyntaxError;

class CsvService
{
    const EXTENSION = 'csv';

    private Reader $csv;

    private bool $test_mode;

    private array $failed_inserted = [];

    private array $updated_rows = [];

    private int $total_success_count = 0;

    private string $file_name;

    private array $headers = [
        "Product Code",
        "Product Name",
        "Product Description",
        "Stock",
        "Cost in GBP",
        "Discontinued"
    ];

    public function __construct(string $file_name, bool $test_mode = false)
    {
        $this->test_mode = $test_mode;
        $this->file_name = $file_name;

        $this->formatExtension();

        $stream = fopen(storage_path() . '/csv/' . $this->file_name, 'r');
        $this->csv = Reader::createFromStream($stream);
    }

    /**
     * @throws Exception|SyntaxError
     */
    private function checkHeaders(): void {
        $this->csv->setHeaderOffset(0);

        $headers = $this->csv->getHeader();

        foreach ($headers as $header) {
            if (!in_array($header, $this->headers)) {
                throw new \Exception("Invalid header: " . $header, CsvException::INVALID_HEADER);
            }
        }
    }

    private function updateCsvRow($record): bool {
        $data = DB::table('product_data')->where('productCode', $record['Product Code']);

        $first = $data->first();

        if ($first) {
            if ($first->discontinued == (!empty($record['Discontinued']) && $record['Discontinued'] === 'yes') &&
                $first->productCost === (float)$record['Cost in GBP'] &&
                $first->productStock === (int)$record['Stock'] &&
                $first->productDesc === $record['Product Description'] &&
                $first->productName === $record['Product Name']
            ) {
                return true;
            }

            $data->update([
                'discontinued' => !empty($record['Discontinued']) && $record['Discontinued'] === 'yes',
                'productCost' => (float)$record['Cost in GBP'],
                'productStock' => (int)$record['Stock'],
                'productDesc' => $record['Product Description'],
                'productName' => $record['Product Name'],
            ]);

            $this->updated_rows[] = $record['Product Code'];

            return true;
        }

        return false;
    }

    private function saveCsvRow($record): void
    {
        $product_data = new ProductData();
        $product_data->productName = $record['Product Name'];
        $product_data->productDesc = $record['Product Description'];
        $product_data->productCode = $record['Product Code'];
        $product_data->discontinued = !empty($record['Discontinued']) && $record['Discontinued'] === 'yes';
        $product_data->productCost = (float)$record['Cost in GBP'];
        $product_data->productStock = (int)$record['Stock'];

        $product_data->save();
    }

    private function checkFields($record): bool {
        if (empty($record['Product Name']) || empty($record['Product Description']) || empty($record['Product Code'])) {
            $this->failed_inserted[] = $record['Product Code'];
            return false;
        }

        if (empty($record['Cost in GBP']) || is_null($record['Stock'])) {
            $this->failed_inserted[] = $record['Product Code'];
            return false;
        }

        return true;
    }

    private function checkConditions($record): bool {
        $cost  = floatval($record['Cost in GBP']);
        $stock = floatval($record['Stock']);

        if ($cost < 5 || $stock < 10) {
            return false;
        } else if ($cost > 1000) {
            return false;
        } else if (!empty($record['Discontinued']) && $record['Discontinued'] !== 'yes') {
            return false;
        }

        return true;
    }

    public function readCsv(): void {
        $this->checkHeaders();

        $stmt = Statement::create();

        $records = $stmt->process($this->csv);

        foreach ($records as $record) {
            if (!$this->checkFields($record)) {
                continue;
            }

            if (!$this->checkConditions($record)) {
                continue;
            }

            if ($this->test_mode) {
                if ($this->updateCsvRow($record)) {
                    $this->total_success_count++;
                    continue;
                }

                $this->saveCsvRow($record);
            }

            $this->total_success_count++;
        }
    }

    public function getStatistics(): array {
        return [
            'total_count'       => $this->total_success_count,
            'failed_inserted'   => $this->failed_inserted,
            'updated_rows'      => $this->updated_rows,
            'csv_report'        => $this->csv->toString(),
        ];
    }

    /**
     * @throws \Exception
     */
    public function formatExtension(): void {
        $file_array = explode('.', $this->file_name);

        if (count($file_array) === 1) {
            $this->file_name = $this->file_name . '.' .  self::EXTENSION;
        } else {
            $extension = array_pop($file_array);

            if ($extension !== self::EXTENSION) {
                throw new \Exception("Invalid extension $extension. We can only use " . self::EXTENSION . ' extension', CsvException::INVALID_EXTENSION);
            }
        }
    }
}
