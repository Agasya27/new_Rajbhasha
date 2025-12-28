SET NAMES utf8mb4;

-- Sample report with minimal JSON
INSERT INTO reports (user_id, unit_id, status, period_quarter, period_year, data_json)
VALUES (1, 1, 'submitted', 1, YEAR(CURDATE()), '{
  "sec1_total_issued": 100,
  "sec1_issued_in_hindi": 60,
  "sec2_received_in_hindi": 40,
  "sec2_replied_in_hindi": 30
}');
