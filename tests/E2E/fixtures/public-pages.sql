-- Fixture data for public-pages E2E tests
-- This fixture creates a test scenario for testing public tournament pages navigation.
--
-- Design notes:
-- - Tournament 1001 with 8 tables (1-4 Volkus, 5-8 Tomb World)
-- - 16 players across 8 pairings per round
-- - Round 1: Published (is_published = 1)
-- - Round 2: Not published (is_published = 0)

-- Clean up any existing test data (in correct order due to foreign keys)
DELETE FROM allocations WHERE round_id IN (SELECT id FROM rounds WHERE tournament_id = 1001);
DELETE FROM rounds WHERE tournament_id = 1001;
DELETE FROM players WHERE tournament_id = 1001;
DELETE FROM tables WHERE tournament_id = 1001;
DELETE FROM tournaments WHERE id = 1001;

-- Tournament
INSERT INTO tournaments (id, name, bcp_event_id, bcp_url, table_count, admin_token)
VALUES (1001, 'Public Page Test', 'publicPageTest',
        'https://www.bestcoastpairings.com/event/publicPageTest', 8, 'pubPagesToken123');

-- Tables (4 Volkus terrain_type_id=1, 4 Tomb World terrain_type_id=2)
INSERT INTO tables (id, tournament_id, table_number, terrain_type_id) VALUES
(2001, 1001, 1, 1), (2002, 1001, 2, 1), (2003, 1001, 3, 1), (2004, 1001, 4, 1),
(2005, 1001, 5, 2), (2006, 1001, 6, 2), (2007, 1001, 7, 2), (2008, 1001, 8, 2);

-- 16 Players
INSERT INTO players (id, tournament_id, bcp_player_id, name, total_score) VALUES
(2001, 1001, 'pp1', 'Alice Test', 20),
(2002, 1001, 'pp2', 'Bob Test', 18),
(2003, 1001, 'pp3', 'Charlie Test', 16),
(2004, 1001, 'pp4', 'Diana Test', 14),
(2005, 1001, 'pp5', 'Edward Test', 12),
(2006, 1001, 'pp6', 'Fiona Test', 10),
(2007, 1001, 'pp7', 'George Test', 8),
(2008, 1001, 'pp8', 'Hannah Test', 6),
(2009, 1001, 'pp9', 'Ivan Test', 5),
(2010, 1001, 'pp10', 'Julia Test', 4),
(2011, 1001, 'pp11', 'Kevin Test', 3),
(2012, 1001, 'pp12', 'Laura Test', 2),
(2013, 1001, 'pp13', 'Mike Test', 1),
(2014, 1001, 'pp14', 'Nancy Test', 1),
(2015, 1001, 'pp15', 'Oscar Test', 0),
(2016, 1001, 'pp16', 'Paula Test', 0);

-- Round 1 (Published) & Round 2 (Not Published)
INSERT INTO rounds (id, tournament_id, round_number, is_published) VALUES
(2001, 1001, 1, 1),
(2002, 1001, 2, 0);

-- Round 1 Allocations (8 games on tables 1-8)
INSERT INTO allocations (id, round_id, table_id, player1_id, player2_id, player1_score, player2_score, bcp_table_number, allocation_reason) VALUES
(2001, 2001, 2001, 2001, 2002, 20, 18, 1, '{"conflicts":[]}'),
(2002, 2001, 2002, 2003, 2004, 16, 14, 2, '{"conflicts":[]}'),
(2003, 2001, 2003, 2005, 2006, 12, 10, 3, '{"conflicts":[]}'),
(2004, 2001, 2004, 2007, 2008, 8, 6, 4, '{"conflicts":[]}'),
(2005, 2001, 2005, 2009, 2010, 5, 4, 5, '{"conflicts":[]}'),
(2006, 2001, 2006, 2011, 2012, 3, 2, 6, '{"conflicts":[]}'),
(2007, 2001, 2007, 2013, 2014, 1, 1, 7, '{"conflicts":[]}'),
(2008, 2001, 2008, 2015, 2016, 0, 0, 8, '{"conflicts":[]}');

-- Round 2 Allocations (8 games, shuffled)
INSERT INTO allocations (id, round_id, table_id, player1_id, player2_id, player1_score, player2_score, bcp_table_number, allocation_reason) VALUES
(2009, 2002, 2001, 2003, 2004, 16, 14, 1, '{"conflicts":[]}'),
(2010, 2002, 2002, 2005, 2006, 12, 10, 2, '{"conflicts":[]}'),
(2011, 2002, 2003, 2009, 2010, 5, 4, 3, '{"conflicts":[]}'),
(2012, 2002, 2004, 2013, 2014, 1, 1, 4, '{"conflicts":[]}'),
(2013, 2002, 2005, 2001, 2002, 20, 18, 5, '{"conflicts":[]}'),
(2014, 2002, 2006, 2011, 2012, 3, 2, 6, '{"conflicts":[]}'),
(2015, 2002, 2007, 2007, 2008, 8, 6, 7, '{"conflicts":[]}'),
(2016, 2002, 2008, 2015, 2016, 0, 0, 8, '{"conflicts":[]}');
