-- Fixture data for allocation-editing E2E tests
-- This fixture creates a complete test scenario for testing table allocation edits,
-- including conflict detection for table collisions and table reuse.
--
-- Design notes:
-- - Tournament 1000 with 8 tables (1-4 Volkus, 5-8 Tomb World)
-- - 16 players across 8 pairings per round
-- - Round 1: Original allocations
-- - Round 2: Shuffled to create terrain reuse warnings and enable TABLE_REUSE via swap
--
-- To trigger TABLE_REUSE conflict: Swap Table 4 and Table 7 in Round 2
--   - Table 4 has p13/p14 who were on Table 7 in R1
--   - Table 7 has p7/p8 who were on Table 4 in R1

-- Use the test database
-- Note: The connection should already be using the correct database

-- Clean up any existing test data (in correct order due to foreign keys)
DELETE FROM allocations WHERE round_id IN (SELECT id FROM rounds WHERE tournament_id = 1000);
DELETE FROM rounds WHERE tournament_id = 1000;
DELETE FROM players WHERE tournament_id = 1000;
DELETE FROM tables WHERE tournament_id = 1000;
DELETE FROM tournaments WHERE id = 1000;

-- Tournament
INSERT INTO tournaments (id, name, bcp_event_id, bcp_url, table_count, admin_token)
VALUES (1000, 'Allocation Edit Test', 'allocEditTest',
        'https://www.bestcoastpairings.com/event/allocEditTest', 8, 'testEditToken123');

-- Tables (4 Volkus terrain_type_id=1, 4 Tomb World terrain_type_id=2)
INSERT INTO tables (id, tournament_id, table_number, terrain_type_id) VALUES
(1001, 1000, 1, 1), (1002, 1000, 2, 1), (1003, 1000, 3, 1), (1004, 1000, 4, 1),
(1005, 1000, 5, 2), (1006, 1000, 6, 2), (1007, 1000, 7, 2), (1008, 1000, 8, 2);

-- 16 Players
INSERT INTO players (id, tournament_id, bcp_player_id, name, total_score, faction) VALUES
(1001, 1000, 'p1', 'Alice Smith', 20, 'Corsair Voidscarred'),
(1002, 1000, 'p2', 'Bob Jones', 18, 'Nemesis Claw'),
(1003, 1000, 'p3', 'Charlie Brown', 16, 'Blades of Khaine'),
(1004, 1000, 'p4', 'Diana Prince', 14, 'Warpcoven'),
(1005, 1000, 'p5', 'Edward Stone', 12, 'Pathfinders'),
(1006, 1000, 'p6', 'Fiona Green', 10, 'Legionaries'),
(1007, 1000, 'p7', 'George White', 8, 'Kommandos'),
(1008, 1000, 'p8', 'Hannah Black', 6, 'Intercession Squad'),
(1009, 1000, 'p9', 'Ivan Red', 5, 'Hand of the Archon'),
(1010, 1000, 'p10', 'Julia Blue', 4, 'Kasrkin'),
(1011, 1000, 'p11', 'Kevin Yellow', 3, 'Hierotek Circle'),
(1012, 1000, 'p12', 'Laura Purple', 2, 'Void-Dancer Troupe'),
(1013, 1000, 'p13', 'Mike Orange', 1, 'Hunter Clade'),
(1014, 1000, 'p14', 'Nancy Pink', 1, 'Wyrmblade'),
(1015, 1000, 'p15', 'Oscar Grey', 0, 'Farstalker Kinband'),
(1016, 1000, 'p16', 'Paula Silver', 0, 'Phobos Strike Team');

-- Round 1 & Round 2
INSERT INTO rounds (id, tournament_id, round_number, is_published) VALUES
(1001, 1000, 1, 0),
(1002, 1000, 2, 0);

-- Round 1 Allocations (8 games on tables 1-8)
-- Table 1 (Volkus): p1 vs p2
-- Table 2 (Volkus): p3 vs p4
-- Table 3 (Volkus): p5 vs p6
-- Table 4 (Volkus): p7 vs p8 <- key: p7/p8 on Table 4
-- Table 5 (Tomb World): p9 vs p10
-- Table 6 (Tomb World): p11 vs p12
-- Table 7 (Tomb World): p13 vs p14 <- key: p13/p14 on Table 7
-- Table 8 (Tomb World): p15 vs p16
INSERT INTO allocations (id, round_id, table_id, player1_id, player2_id, player1_score, player2_score, bcp_table_number, allocation_reason) VALUES
(1001, 1001, 1001, 1001, 1002, 20, 18, 1, '{"conflicts":[]}'),
(1002, 1001, 1002, 1003, 1004, 16, 14, 2, '{"conflicts":[]}'),
(1003, 1001, 1003, 1005, 1006, 12, 10, 3, '{"conflicts":[]}'),
(1004, 1001, 1004, 1007, 1008, 8, 6, 4, '{"conflicts":[]}'),
(1005, 1001, 1005, 1009, 1010, 5, 4, 5, '{"conflicts":[]}'),
(1006, 1001, 1006, 1011, 1012, 3, 2, 6, '{"conflicts":[]}'),
(1007, 1001, 1007, 1013, 1014, 1, 1, 7, '{"conflicts":[]}'),
(1008, 1001, 1008, 1015, 1016, 0, 0, 8, '{"conflicts":[]}');

-- Round 2 Allocations (shuffled to create terrain reuse warnings)
-- Designed so swapping Table 4 and Table 7 creates TABLE_REUSE conflicts:
-- Table 1 (Volkus): p3 vs p4 (were on Table 2 Volkus - TERRAIN_REUSE)
-- Table 2 (Volkus): p5 vs p6 (were on Table 3 Volkus - TERRAIN_REUSE)
-- Table 3 (Volkus): p9 vs p10 (were on Table 5 Tomb World - different terrain)
-- Table 4 (Volkus): p13 vs p14 (were on Table 7 Tomb World) <- swap target
-- Table 5 (Tomb World): p1 vs p2 (were on Table 1 Volkus - different terrain)
-- Table 6 (Tomb World): p11 vs p12 (were on Table 6 Tomb World - TERRAIN_REUSE)
-- Table 7 (Tomb World): p7 vs p8 (were on Table 4 Volkus) <- swap target
-- Table 8 (Tomb World): p15 vs p16 (were on Table 8 Tomb World - TERRAIN_REUSE)
INSERT INTO allocations (id, round_id, table_id, player1_id, player2_id, player1_score, player2_score, bcp_table_number, allocation_reason) VALUES
(1009, 1002, 1001, 1003, 1004, 16, 14, 1, '{"conflicts":[{"type":"TERRAIN_REUSE","message":"Charlie Brown already played on Volkus terrain"},{"type":"TERRAIN_REUSE","message":"Diana Prince already played on Volkus terrain"}]}'),
(1010, 1002, 1002, 1005, 1006, 12, 10, 2, '{"conflicts":[{"type":"TERRAIN_REUSE","message":"Edward Stone already played on Volkus terrain"},{"type":"TERRAIN_REUSE","message":"Fiona Green already played on Volkus terrain"}]}'),
(1011, 1002, 1003, 1009, 1010, 5, 4, 3, '{"conflicts":[]}'),
(1012, 1002, 1004, 1013, 1014, 1, 1, 4, '{"conflicts":[]}'),
(1013, 1002, 1005, 1001, 1002, 20, 18, 5, '{"conflicts":[]}'),
(1014, 1002, 1006, 1011, 1012, 3, 2, 6, '{"conflicts":[{"type":"TERRAIN_REUSE","message":"Kevin Yellow already played on Tomb World terrain"},{"type":"TERRAIN_REUSE","message":"Laura Purple already played on Tomb World terrain"}]}'),
(1015, 1002, 1007, 1007, 1008, 8, 6, 7, '{"conflicts":[]}'),
(1016, 1002, 1008, 1015, 1016, 0, 0, 8, '{"conflicts":[{"type":"TERRAIN_REUSE","message":"Oscar Grey already played on Tomb World terrain"},{"type":"TERRAIN_REUSE","message":"Paula Silver already played on Tomb World terrain"}]}');
