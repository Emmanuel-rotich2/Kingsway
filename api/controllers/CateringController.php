<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Modules\reports\MealReportManager;
use InvalidArgumentException;
use Throwable;

/**
 * CateringController
 *
 * Exposes catering endpoints only. Business queries remain in the canonical
 * MealReportManager under api/modules/reports.
 */
class CateringController extends BaseController
{
    /** @var MealReportManager */
    private $reports;

    public function __construct()
    {
        parent::__construct();
        $this->reports = new MealReportManager();
    }

    public function index($id = null, $data = [], $segments = [])
    {
        return $this->success(['message' => 'Catering API is running']);
    }

    public function getStats($id = null, $data = [], $segments = [])
    {
        return $this->delegate(function () {
            return $this->reports->getStats($_GET['date'] ?? null);
        });
    }

    public function getMenu($id = null, $data = [], $segments = [])
    {
        return $this->delegate(function () {
            return $this->reports->getMenu($_GET['date'] ?? null);
        });
    }

    public function getFoodStock($id = null, $data = [], $segments = [])
    {
        return $this->delegate(function () {
            return $this->reports->getFoodStock(
                !empty($_GET['low_stock']),
                (int) ($_GET['limit'] ?? 50)
            );
        });
    }

    private function delegate(callable $operation)
    {
        try {
            $result = $operation();
            if (($result['success'] ?? false) !== true) {
                return $this->badRequest(
                    $result['message'] ?? $result['error'] ?? 'Catering operation failed'
                );
            }
            return $this->success($result['data'] ?? null);
        } catch (InvalidArgumentException $error) {
            return $this->badRequest($error->getMessage());
        } catch (Throwable $error) {
            return $this->serverError($error->getMessage());
        }
    }
}
