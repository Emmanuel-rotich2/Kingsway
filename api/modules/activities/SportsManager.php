<?php
namespace App\API\Modules\activities;
use App\API\Services\ReadReplicaService;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;

/**
 * SportsManager - CRUD and reporting for the sports module
 * Teams, team members, fixtures, match results, and standings.
 *
 * Data model: sports_teams, sports_team_members, sports_fixtures,
 * sports_player_stats, sports_standings (see
 * database/migrations/001_create_sports_tables.sql).
 *
 * Standings are computed on the fly from completed fixtures so the table
 * never drifts from the recorded results.
 */
class SportsManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('sports');
    }

    /**
     * List teams with member counts, records, and coach names.
     * @param array $params Filters: search, sport_type, season, status, limit
     */
    public function listTeams($params = [])
    {
        try {
            $where = ['1=1'];
            $bindings = [];

            if (!empty($params['search'])) {
                $where[] = '(t.name LIKE ? OR t.sport_type LIKE ?)';
                $term = '%' . $params['search'] . '%';
                $bindings[] = $term;
                $bindings[] = $term;
            }
            if (!empty($params['sport_type'])) {
                $where[] = 't.sport_type = ?';
                $bindings[] = $params['sport_type'];
            }
            if (!empty($params['season'])) {
                $where[] = 't.season = ?';
                $bindings[] = $params['season'];
            }
            if (!empty($params['status'])) {
                $where[] = 't.status = ?';
                $bindings[] = $params['status'];
            }

            $limit = min(max((int) ($params['limit'] ?? 200), 1), 500);
            $whereClause = implode(' AND ', $where);

            $sql = "
                SELECT t.id, t.name, t.sport_type, t.season, t.status,
                       t.wins, t.losses, t.draws,
                       t.description,
                       CONCAT(p.first_name, ' ', COALESCE(p.middle_name, ''), ' ', p.last_name) AS coach,
                       COUNT(DISTINCT m.id) AS member_count
                FROM sports_teams t
                LEFT JOIN " . ReadReplicaService::qualifiedRef("staff") . " st ON st.id = t.coach_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = st.person_id
                LEFT JOIN sports_team_members m ON m.team_id = t.id AND m.status = 'active'
                WHERE $whereClause
                GROUP BY t.id
                ORDER BY t.sport_type, t.name
                LIMIT $limit
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($teams as &$team) {
                $team['coach'] = trim($team['coach'] ?? '') ?: null;
            }
            unset($team);

            return ['success' => true, 'data' => $teams];
        } catch (Exception $e) {
            $this->logError($e, 'Failed to list sports teams');
            return $this->errorResponse('Failed to load sports teams');
        }
    }

    /**
     * Get a single team with its record and member count.
     */
    public function getTeam($id)
    {
        try {
            $sql = "
                SELECT t.id, t.name, t.sport_type, t.season, t.status,
                       t.wins, t.losses, t.draws,
                       t.description,
                       CONCAT(p.first_name, ' ', COALESCE(p.middle_name, ''), ' ', p.last_name) AS coach,
                       COUNT(DISTINCT m.id) AS member_count
                FROM sports_teams t
                LEFT JOIN " . ReadReplicaService::qualifiedRef("staff") . " st ON st.id = t.coach_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = st.person_id
                LEFT JOIN sports_team_members m ON m.team_id = t.id AND m.status = 'active'
                WHERE t.id = ?
                GROUP BY t.id
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([(int) $id]);
            $team = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$team) {
                return ['success' => false, 'message' => 'Team not found', 'data' => null];
            }
            $team['coach'] = trim($team['coach'] ?? '') ?: null;

            return ['success' => true, 'data' => $team];
        } catch (Exception $e) {
            $this->logError($e, 'Failed to get sports team');
            return $this->errorResponse('Failed to load team');
        }
    }

    /**
     * Create a sports team. The coach field is resolved to a staff id by name
     * (best effort) and the team is attached to the configured Sports category.
     */
    public function createTeam($data, $userId)
    {
        try {
            $name = trim($data['name'] ?? '');
            $sportType = trim($data['sport_type'] ?? '');
            if ($name === '' || $sportType === '') {
                return ['success' => false, 'message' => 'Team name and sport type are required', 'data' => null];
            }

            $categoryId = $this->resolveSportsCategoryId();
            if ($categoryId === null) {
                return ['success' => false, 'message' => 'Sports activity category is not configured', 'data' => null];
            }

            $coachId = null;
            $coach = trim($data['coach'] ?? '');
            if ($coach !== '') {
                $coachId = $this->resolveStaffIdByName($coach);
            }

            $sql = "
                INSERT INTO sports_teams (
                    name, sport_type, category_id, coach_id, description,
                    season, status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, NOW())
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $name,
                $sportType,
                $categoryId,
                $coachId,
                $data['description'] ?? null,
                $data['season'] ?? null,
                $userId ?: null,
            ]);

            $teamId = (int) $this->db->lastInsertId();

            return ['success' => true, 'data' => ['id' => $teamId], 'message' => 'Team created successfully'];
        } catch (Exception $e) {
            $this->logError($e, 'Failed to create sports team');
            return $this->errorResponse('Failed to create team');
        }
    }

    /**
     * List team members with student names and current class.
     */
    public function listTeamMembers($params = [])
    {
        try {
            $teamId = (int) ($params['team_id'] ?? 0);
            if ($teamId <= 0) {
                return ['success' => false, 'message' => 'team_id is required', 'data' => []];
            }

            $sql = "
                SELECT m.id, m.team_id, m.student_id, m.position, m.jersey_number, m.status,
                       CONCAT(p.first_name, ' ', COALESCE(p.middle_name, ''), ' ', p.last_name) AS player_name,
                       COALESCE(e.class_stream, '') AS class_name
                FROM sports_team_members m
                JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = m.student_id
                JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = s.person_id
                LEFT JOIN vw_current_enrollments e ON e.student_id = m.student_id
                WHERE m.team_id = ?
                ORDER BY m.jersey_number IS NULL, m.jersey_number, player_name
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$teamId]);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($members as &$m) {
                $m['player_name'] = trim($m['player_name'] ?? '');
            }
            unset($m);

            return ['success' => true, 'data' => $members];
        } catch (Exception $e) {
            $this->logError($e, 'Failed to list sports team members');
            return $this->errorResponse('Failed to load team roster');
        }
    }

    /**
     * List fixtures with team names. Supports status/team_id/season filters.
     */
    public function listFixtures($params = [])
    {
        try {
            $where = ['1=1'];
            $bindings = [];

            if (!empty($params['status'])) {
                $where[] = 'f.status = ?';
                $bindings[] = $params['status'];
            }
            if (!empty($params['team_id'])) {
                $where[] = 'f.team_id = ?';
                $bindings[] = (int) $params['team_id'];
            }
            if (!empty($params['season'])) {
                $where[] = 'f.season = ?';
                $bindings[] = $params['season'];
            }

            $limit = min(max((int) ($params['limit'] ?? 200), 1), 500);
            $whereClause = implode(' AND ', $where);

            $sql = "
                SELECT f.id, f.team_id, t.name AS team_name, f.opponent,
                       DATE_FORMAT(f.fixture_date, '%Y-%m-%d') AS fixture_date,
                       DATE_FORMAT(f.fixture_date, '%H:%i') AS time,
                       f.venue, f.fixture_type, f.home_away, f.status,
                       f.our_score, f.opponent_score, f.result, f.season
                FROM " . ReadReplicaService::qualifiedRef("sports_fixtures") . "
                JOIN sports_teams t ON t.id = f.team_id
                WHERE $whereClause
                ORDER BY f.fixture_date DESC, f.id DESC
                LIMIT $limit
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $fixtures = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'data' => $fixtures];
        } catch (Exception $e) {
            $this->logError($e, 'Failed to list sports fixtures');
            return $this->errorResponse('Failed to load fixtures');
        }
    }

    /**
     * Get a single fixture with team name.
     */
    public function getFixture($id)
    {
        try {
            $sql = "
                SELECT f.id, f.team_id, t.name AS team_name, f.opponent,
                       DATE_FORMAT(f.fixture_date, '%Y-%m-%d') AS fixture_date,
                       DATE_FORMAT(f.fixture_date, '%H:%i') AS time,
                       f.venue, f.fixture_type, f.home_away, f.status,
                       f.our_score, f.opponent_score, f.result, f.season,
                       f.match_report, f.referee
                FROM " . ReadReplicaService::qualifiedRef("sports_fixtures") . "
                JOIN sports_teams t ON t.id = f.team_id
                WHERE f.id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([(int) $id]);
            $fixture = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fixture) {
                return ['success' => false, 'message' => 'Fixture not found', 'data' => null];
            }

            return ['success' => true, 'data' => $fixture];
        } catch (Exception $e) {
            $this->logError($e, 'Failed to get sports fixture');
            return $this->errorResponse('Failed to load fixture');
        }
    }

    /**
     * Create a fixture for a team. fixture_date + time are combined into the
     * DATETIME column.
     */
    public function createFixture($data, $userId)
    {
        try {
            $teamId = (int) ($data['team_id'] ?? 0);
            $opponent = trim($data['opponent'] ?? '');
            $date = $data['fixture_date'] ?? '';
            if ($teamId <= 0 || $opponent === '' || $date === '') {
                return ['success' => false, 'message' => 'Team, opponent, and date are required', 'data' => null];
            }

            $time = trim($data['time'] ?? '');
            $fixtureDate = date('Y-m-d H:i:s', strtotime($date . ' ' . ($time !== '' ? $time : '00:00:00')));
            if ($fixtureDate === false) {
                return ['success' => false, 'message' => 'Invalid fixture date', 'data' => null];
            }

            $season = !empty($data['season']) ? $data['season'] : $this->teamSeason($teamId);

            $sql = "
                INSERT INTO sports_fixtures (
                    team_id, opponent, fixture_type, venue, fixture_date,
                    home_away, status, season, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'scheduled', ?, ?, NOW())
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $teamId,
                $opponent,
                $data['fixture_type'] ?? 'friendly',
                $data['venue'] ?? null,
                $fixtureDate,
                $data['home_away'] ?? 'home',
                $season,
                $userId ?: null,
            ]);

            $fixtureId = (int) $this->db->lastInsertId();

            return ['success' => true, 'data' => ['id' => $fixtureId], 'message' => 'Fixture created successfully'];
        } catch (Exception $e) {
            $this->logError($e, 'Failed to create sports fixture');
            return $this->errorResponse('Failed to create fixture');
        }
    }

    /**
     * Record a completed match result and refresh the team's W/L/D counters.
     */
    public function recordResult($id, $data, $userId)
    {
        try {
            $fixtureId = (int) $id;
            if ($fixtureId <= 0) {
                return ['success' => false, 'message' => 'Fixture ID is required', 'data' => null];
            }

            $stmt = $this->db->prepare('SELECT id, team_id, status FROM ' . ReadReplicaService::qualifiedRef('sports_fixtures') . ' WHERE id = ?');
            $stmt->execute([$fixtureId]);
            $fixture = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$fixture) {
                return ['success' => false, 'message' => 'Fixture not found', 'data' => null];
            }

            $ourScore = (int) ($data['our_score'] ?? 0);
            $opponentScore = (int) ($data['opponent_score'] ?? 0);
            $result = $data['result'] ?? ($ourScore > $opponentScore ? 'win' : ($ourScore < $opponentScore ? 'loss' : 'draw'));

            if (!in_array($result, ['win', 'loss', 'draw'], true)) {
                return ['success' => false, 'message' => 'Result must be win, loss, or draw', 'data' => null];
            }

            $sql = "
                UPDATE sports_fixtures
                SET our_score = ?, opponent_score = ?, result = ?,
                    status = 'completed', updated_at = NOW()
                WHERE id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$ourScore, $opponentScore, $result, $fixtureId]);

            $this->refreshTeamRecord((int) $fixture['team_id']);

            return ['success' => true, 'data' => ['id' => $fixtureId], 'message' => 'Result recorded successfully'];
        } catch (Exception $e) {
            $this->logError($e, 'Failed to record sports result');
            return $this->errorResponse('Failed to record result');
        }
    }

    /**
     * Compute standings from completed fixtures, ranked by points then goal
     * difference. Each row carries team_name and a form letter for the most
     * recent completed match.
     */
    public function getStandings($params = [])
    {
        try {
            $where = ['t.status = ?'];
            $bindings = ['active'];

            if (!empty($params['sport_type'])) {
                $where[] = 't.sport_type = ?';
                $bindings[] = $params['sport_type'];
            }
            if (!empty($params['season'])) {
                $where[] = 't.season = ?';
                $bindings[] = $params['season'];
            }

            $limit = min(max((int) ($params['limit'] ?? 50), 1), 500);
            $whereClause = implode(' AND ', $where);

            $sql = "
                SELECT t.id AS team_id, t.name AS team_name, t.sport_type, t.season,
                       COUNT(f.id) AS played,
                       COALESCE(SUM(f.result = 'win'), 0) AS wins,
                       COALESCE(SUM(f.result = 'draw'), 0) AS draws,
                       COALESCE(SUM(f.result = 'loss'), 0) AS losses,
                       COALESCE(SUM(f.our_score), 0) AS goals_for,
                       COALESCE(SUM(f.opponent_score), 0) AS goals_against,
                       (COALESCE(SUM(f.result = 'win'), 0) * 3 + COALESCE(SUM(f.result = 'draw'), 0) * 1) AS points,
                       (SELECT f3.result FROM sports_fixtures f3
                         WHERE f3.team_id = t.id AND f3.status = 'completed'
                         ORDER BY f3.fixture_date DESC, f3.id DESC LIMIT 1) AS last_result
                FROM sports_teams t
                LEFT JOIN sports_fixtures f ON f.team_id = t.id AND f.status = 'completed'
                WHERE $whereClause
                GROUP BY t.id
                ORDER BY points DESC, (COALESCE(SUM(f.our_score), 0) - COALESCE(SUM(f.opponent_score), 0)) DESC, wins DESC
                LIMIT $limit
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$row) {
                $row['played'] = (int) $row['played'];
                $row['wins'] = (int) $row['wins'];
                $row['draws'] = (int) $row['draws'];
                $row['losses'] = (int) $row['losses'];
                $row['goals_for'] = (int) $row['goals_for'];
                $row['goals_against'] = (int) $row['goals_against'];
                $row['points'] = (int) $row['points'];
                $lastResult = $row['last_result'] ?? '';
                $row['form'] = $lastResult === 'win' ? 'W' : ($lastResult === 'loss' ? 'L' : ($lastResult === 'draw' ? 'D' : '-'));
                unset($row['last_result']);
            }
            unset($row);

            return ['success' => true, 'data' => $rows];
        } catch (Exception $e) {
            $this->logError($e, 'Failed to compute sports standings');
            return $this->errorResponse('Failed to load standings');
        }
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function resolveSportsCategoryId()
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM activity_categories WHERE name LIKE ? AND status = 'active' ORDER BY id LIMIT 1"
        );
        $stmt->execute(['%sport%']);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function resolveStaffIdByName($name)
    {
        $stmt = $this->db->prepare(
            "SELECT st.staff_id
             FROM " . ReadReplicaService::qualifiedRef("staff_directory") . " st
             WHERE CONCAT(st.first_name, ' ', st.last_name) = ?
                OR CONCAT(st.first_name, ' ', COALESCE(st.middle_name, ''), ' ', st.last_name) = ?
             ORDER BY st.staff_id LIMIT 1"
        );
        $stmt->execute([$name, $name]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function teamSeason($teamId)
    {
        $stmt = $this->db->prepare('SELECT season FROM sports_teams WHERE id = ?');
        $stmt->execute([(int) $teamId]);
        $season = $stmt->fetchColumn();
        return $season ?: null;
    }

    private function refreshTeamRecord($teamId)
    {
        $sql = "
            UPDATE sports_teams t
            SET t.wins = (SELECT COUNT(*) FROM sports_fixtures f WHERE f.team_id = t.id AND f.status = 'completed' AND f.result = 'win'),
                t.draws = (SELECT COUNT(*) FROM sports_fixtures f WHERE f.team_id = t.id AND f.status = 'completed' AND f.result = 'draw'),
                t.losses = (SELECT COUNT(*) FROM sports_fixtures f WHERE f.team_id = t.id AND f.status = 'completed' AND f.result = 'loss'),
                t.updated_at = NOW()
            WHERE t.id = ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$teamId]);
    }
}
