<?php
// /app/admin/utils/risk_score.php

class RiskScoreCalculator
{
    private $conn;

    public function __construct($db_connection)
    {
        $this->conn = $db_connection;
    }

    /**
     * Calculate risk score for a household based on multiple factors
     * Returns score from 0-100 (higher = more vulnerable)
     */
    public function calculateHouseholdRisk($household_id)
    {
        // Get household data
        $stmt = $this->conn->prepare("
            SELECT h.* FROM households h WHERE h.id = ?
        ");
        $stmt->bind_param("i", $household_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $household = $result->fetch_assoc();

        if (!$household) {
            return 0;
        }

        $score = 0;

        // ============================================
        // FACTOR 1: Income Level (40% weight)
        // ============================================
        $monthly_income = floatval($household['income_monthly'] ?? 0);
        $household_size = intval($household['household_size'] ?? 1);
        $per_capita_income = $household_size > 0 ? $monthly_income / $household_size : 0;

        // Poverty threshold in PH (approximate)
        if ($per_capita_income < 1000) {
            $score += 40; // Extreme poverty
        } elseif ($per_capita_income < 2000) {
            $score += 30; // Below poverty line
        } elseif ($per_capita_income < 3000) {
            $score += 20; // Near poverty line
        } elseif ($per_capita_income < 5000) {
            $score += 10; // Low income but above poverty
        }
        // else: +0 for middle income and above

        // ============================================
        // FACTOR 2: Household Size (20% weight)
        // ============================================
        $members_count = intval($household['household_size'] ?? 1);

        if ($members_count > 8) {
            $score += 20; // Very large family
        } elseif ($members_count > 6) {
            $score += 15; // Large family
        } elseif ($members_count > 4) {
            $score += 10; // Medium-large family
        } elseif ($members_count > 2) {
            $score += 5;  // Small family
        }

        // ============================================
        // FACTOR 3: Vulnerability Indicators (20% weight)
        // ============================================
        // 4Ps Status (not in 4Ps increases risk)
        if (($household['four_ps'] ?? 'No') !== 'Yes') {
            $score += 8;
        }

        // Disability
        if (($household['disability'] ?? 'No') === 'Yes') {
            $score += 6;
        }

        // Senior Citizen
        if (($household['senior_citizen'] ?? 'No') === 'Yes') {
            $score += 6;
        }

        // ============================================
        // FACTOR 4: Living Conditions (20% weight)
        // ============================================
        // Housing Type
        $housing = $household['housing_type'] ?? '';
        if (empty($housing) || $housing === 'Makeshift') {
            $score += 8;
        } elseif ($housing === 'Wood') {
            $score += 5;
        } elseif ($housing === 'Semi-Concrete') {
            $score += 3;
        }
        // Concrete: +0

        // Water Source
        $water = $household['water_source'] ?? '';
        if (empty($water) || $water === 'Others') {
            $score += 6;
        } elseif (strpos($water, 'Level I') !== false) {
            $score += 4; // Point source
        } elseif (strpos($water, 'Level II') !== false) {
            $score += 2; // Communal faucet
        }
        // Level III (Waterworks): +0

        // Toilet Type
        $toilet = $household['toilet_type'] ?? '';
        if (empty($toilet) || $toilet === 'None') {
            $score += 6;
        } elseif ($toilet === 'Antipolo') {
            $score += 3;
        }
        // Water-sealed: +0

        // ============================================
        // FACTOR 5: Employment Status (bonus factor)
        // ============================================
        $employment = $household['employment'] ?? '';
        if ($employment === 'Unemployed') {
            $score += 5;
        } elseif ($employment === 'Student' || $employment === 'Retired') {
            $score += 3;
        }

        // Ensure score doesn't exceed 100
        return min(100, round($score));
    }

    /**
     * Get risk level label based on score
     */
    public function getRiskLevel($score)
    {
        if ($score <= 30) {
            return 'Low';
        } elseif ($score <= 60) {
            return 'Medium';
        } else {
            return 'High';
        }
    }

    /**
     * Get color code for risk level
     */
    public function getRiskColor($score)
    {
        if ($score <= 30) {
            return '#28a745'; // Green
        } elseif ($score <= 60) {
            return '#ffc107'; // Yellow
        } else {
            return '#dc3545'; // Red
        }
    }

    /**
     * Batch calculate risk scores for all households in a barangay
     */
    public function batchCalculateBarangay($barangay_id = null)
    {
        $query = "SELECT id FROM households";
        if ($barangay_id) {
            $query .= " WHERE barangay_id = " . intval($barangay_id);
        }

        $result = $this->conn->query($query);
        $updated = 0;

        while ($row = $result->fetch_assoc()) {
            $score = $this->calculateHouseholdRisk($row['id']);
            $update = $this->conn->prepare("UPDATE households SET risk_score = ? WHERE id = ?");
            $update->bind_param("ii", $score, $row['id']);
            if ($update->execute()) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Get summary statistics for dashboard
     */
    public function getRiskSummary($barangay_id = null)
    {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN risk_score <= 30 THEN 1 ELSE 0 END) as low_risk,
                    SUM(CASE WHEN risk_score > 30 AND risk_score <= 60 THEN 1 ELSE 0 END) as medium_risk,
                    SUM(CASE WHEN risk_score > 60 THEN 1 ELSE 0 END) as high_risk,
                    AVG(risk_score) as average_risk
                  FROM households";

        if ($barangay_id) {
            $query .= " WHERE barangay_id = " . intval($barangay_id);
        }

        $result = $this->conn->query($query);
        return $result->fetch_assoc();
    }
}
?>