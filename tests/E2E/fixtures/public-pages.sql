-- Fixture data for public-pages E2E tests
-- This fixture creates a test scenario for testing public tournament pages navigation.
--
-- Design notes:
-- - Multiple tournaments for public list behavior:
--   - 1002 Upcoming (future date)
--   - 1001 Live (current date window)
--   - 1003 Finished (past date)
--   - 1004 Fallback (missing date/photo)
-- - Tournament 1001 has round data used by query-based round/leaderboard navigation tests

-- Clean up any existing test data (in correct order due to foreign keys)
DELETE FROM allocations WHERE round_id IN (SELECT id FROM rounds WHERE tournament_id IN (1001, 1002, 1003, 1004));
DELETE FROM rounds WHERE tournament_id IN (1001, 1002, 1003, 1004);
DELETE FROM players WHERE tournament_id IN (1001, 1002, 1003, 1004);
DELETE FROM tables WHERE tournament_id IN (1001, 1002, 1003, 1004);
DELETE FROM tournaments WHERE id IN (1001, 1002, 1003, 1004);

-- Tournaments
INSERT INTO tournaments (id, name, bcp_event_id, bcp_url, photo_url, event_date, event_end_date, table_count, admin_token)
VALUES
(1001, 'Public Page Test', 'publicPageTest',
 'https://www.bestcoastpairings.com/event/publicPageTest',
 'https://example.com/public-page-test.png',
 '2026-04-05T08:00:00.000Z',
 '2026-04-06T20:00:00.000Z',
 8, 'pubPagesToken123'),
(1002, 'Upcoming Open Test', 'upcomingOpenTest',
 'https://www.bestcoastpairings.com/event/upcomingOpenTest',
 'https://example.com/upcoming-open-test.png',
 '2026-07-12T08:00:00.000Z',
 '2026-07-14T20:00:00.000Z',
 4, 'pubPagesToken124'),
(1003, 'Finished Event Test', 'finishedEventTest',
 'https://www.bestcoastpairings.com/event/finishedEventTest',
 'https://example.com/finished-event-test.png',
 '2026-01-10T08:00:00.000Z',
 '2026-01-10T20:00:00.000Z',
 3, 'pubPagesToken125'),
(1004, 'Fallback Data Test', 'fallbackDataTest',
 'https://www.bestcoastpairings.com/event/fallbackDataTest',
 NULL,
 NULL,
 NULL,
 0, 'pubPagesToken126');

-- Tables for tournament 1001 (4 Volkus terrain_type_id=1, 4 Tomb World terrain_type_id=2)
INSERT INTO tables (id, tournament_id, table_number, terrain_type_id) VALUES
(2001, 1001, 1, 1), (2002, 1001, 2, 1), (2003, 1001, 3, 1), (2004, 1001, 4, 1),
(2005, 1001, 5, 2), (2006, 1001, 6, 2), (2007, 1001, 7, 2), (2008, 1001, 8, 2);

-- Tables for tournament 1002 and 1003
INSERT INTO tables (id, tournament_id, table_number, terrain_type_id) VALUES
(2101, 1002, 1, 1), (2102, 1002, 2, 2), (2103, 1002, 3, 2), (2104, 1002, 4, 1),
(2201, 1003, 1, 2), (2202, 1003, 2, 1), (2203, 1003, 3, 2);

-- Players
INSERT INTO players (id, tournament_id, bcp_player_id, name, total_score, faction, placing) VALUES
(2001, 1001, 'pp1', 'Alice Test', 20, 'Corsair Voidscarred', 2),
(2002, 1001, 'pp2', 'Bob Test', 18, 'Nemesis Claw', 1),
(2003, 1001, 'pp3', 'Charlie Test', 16, 'Blades of Khaine', 3),
(2004, 1001, 'pp4', 'Diana Test', 14, 'Warpcoven', 4),
(2005, 1001, 'pp5', 'Edward Test', 12, 'Pathfinders', 5),
(2006, 1001, 'pp6', 'Fiona Test', 10, 'Legionaries', 6),
(2007, 1001, 'pp7', 'George Test', 8, 'Kommandos', 7),
(2008, 1001, 'pp8', 'Hannah Test', 6, 'Intercession Squad', 8),
(2009, 1001, 'pp9', 'Ivan Test', 5, 'Hand of the Archon', 9),
(2010, 1001, 'pp10', 'Julia Test', 4, 'Kasrkin', 10),
(2011, 1001, 'pp11', 'Kevin Test', 3, 'Hierotek Circle', 11),
(2012, 1001, 'pp12', 'Laura Test', 2, 'Void-Dancer Troupe', 12),
(2013, 1001, 'pp13', 'Mike Test', 1, 'Hunter Clade', 13),
(2014, 1001, 'pp14', 'Nancy Test', 1, 'Wyrmblade', 14),
(2015, 1001, 'pp15', 'Oscar Test', 0, 'Farstalker Kinband', 15),
(2016, 1001, 'pp16', 'Paula Test', 0, 'Phobos Strike Team', 16),
(2101, 1002, 'up1', 'Uma Future', 0, 'Kommandos', NULL),
(2102, 1002, 'up2', 'Victor Future', 0, 'Pathfinders', NULL),
(2103, 1002, 'up3', 'Wendy Future', 0, 'Legionaries', NULL),
(2104, 1002, 'up4', 'Xavier Future', 0, 'Intercession Squad', NULL),
(2201, 1003, 'fi1', 'Yara Past', 12, 'Kasrkin', 1),
(2202, 1003, 'fi2', 'Zane Past', 9, 'Blades of Khaine', 2);

-- Round 1 (Published) & Round 2 (Not Published)
INSERT INTO rounds (id, tournament_id, round_number, is_published) VALUES
(2001, 1001, 1, 1),
(2002, 1001, 2, 0),
(2201, 1003, 1, 1);

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
