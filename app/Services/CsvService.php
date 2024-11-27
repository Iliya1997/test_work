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

    const PRODUCT_CODE_HEADER = 'Product Code';
    const PRODUCT_NAME_HEADER = 'Product Name';
    const PRODUCT_DESCRIPTION_HEADER = 'Product Description';
    const STOCK_HEADER = 'Stock';
    const COST_HEADER = 'Cost in GBP';
    const DISCONTINUED_HEADER = 'Discontinued';

    private Reader $csv;

    private bool $test_mode;

    private array $failed_inserted = [];

    private array $updated_rows = [];

    private int $total_success_count = 0;

    private string $file_name;

    private array $headers = [
        self::PRODUCT_CODE_HEADER,
        self::PRODUCT_NAME_HEADER,
        self::PRODUCT_DESCRIPTION_HEADER,
        self::STOCK_HEADER,
        self::COST_HEADER,
        self::DISCONTINUED_HEADER,
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
        $data = DB::table('product_data')->where('productCode', $record[self::PRODUCT_CODE_HEADER]);

        $first = $data->first();

        if ($first) {
            $discontinued = null;

            if (is_null($first->discontinued) && !empty($record['Discontinued']) && trim($record[self::DISCONTINUED_HEADER]) === 'yes') {
                $discontinued = date('Y-m-d H:i:s');
            } else if (!empty($record[self::DISCONTINUED_HEADER]) && trim($record[self::DISCONTINUED_HEADER]) === 'yes') {
                $discontinued = $first->discontinued;
            }

            if ($first->discontinued == $discontinued &&
                $first->productCost === (float)$record[self::COST_HEADER] &&
                $first->productStock === (int)$record[self::STOCK_HEADER] &&
                $first->productDesc === $record[self::PRODUCT_DESCRIPTION_HEADER] &&
                $first->productName === $record[self::PRODUCT_NAME_HEADER]
            ) {
                return true;
            }

            $data->update([
                'discontinued' => $discontinued,
                'productCost' => (float)$record[self::COST_HEADER],
                'productStock' => (int)$record[self::STOCK_HEADER],
                'productDesc' => $record[self::PRODUCT_DESCRIPTION_HEADER],
                'productName' => $record[self::PRODUCT_NAME_HEADER],
            ]);

            $this->updated_rows[] = $record[self::PRODUCT_CODE_HEADER];

            return true;
        }

        return false;
    }

    private function saveCsvRow($record): void
    {
        $product_data = new ProductData();
        $product_data->productName = $record[self::PRODUCT_NAME_HEADER];
        $product_data->productDesc = $record[self::PRODUCT_DESCRIPTION_HEADER];
        $product_data->productCode = $record[self::PRODUCT_CODE_HEADER];
        $product_data->discontinued = !empty($record[self::DISCONTINUED_HEADER]) && $record[self::DISCONTINUED_HEADER] === 'yes' ? date('Y-m-d H:i:s') : null;
        $product_data->productCost = (float)$record[self::COST_HEADER];
        $product_data->productStock = (int)$record[self::STOCK_HEADER];

        $product_data->save();
    }

    private function checkFields($record): bool {
        if (empty($record[self::PRODUCT_NAME_HEADER]) || empty($record[self::PRODUCT_DESCRIPTION_HEADER]) || empty($record[self::PRODUCT_CODE_HEADER])) {
            $this->failed_inserted[] = $record[self::PRODUCT_CODE_HEADER];
            return false;
        }

        if (empty($record[self::COST_HEADER]) || is_null($record[self::STOCK_HEADER])) {
            $this->failed_inserted[] = $record[self::PRODUCT_CODE_HEADER];
            return false;
        }

        return true;
    }

    private function checkConditions($record): bool {
        $cost  = floatval($record[self::COST_HEADER]);
        $stock = floatval($record[self::STOCK_HEADER]);

        if ($cost < 5 || $stock < 10) {
            return false;
        } else if ($cost > 1000) {
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

            if (!$this->test_mode) {
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
