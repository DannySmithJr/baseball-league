# Thursday, March 19th , 2026 - OOTP Baseball 27.1 Build 41
# 
# Dumping data for league_playoff_fixtures.mysql.sql
# Game name: sMLB
# Game date: 1970-07-06
# 
DROP TABLE IF EXISTS `league_playoff_fixtures`;
CREATE TABLE IF NOT EXISTS `league_playoff_fixtures` (`league_id` INT, `team_id0` INT, `team_id1` INT, `winner` INT, `finished` TINYINT, `best_of` SMALLINT, `played` SMALLINT, `round` SMALLINT, `result0` SMALLINT, `result1` SMALLINT);
insert into `league_playoff_fixtures` (`league_id`, `team_id0`, `team_id1`, `winner`, `finished`, `best_of`, `played`, `round`, `result0`, `result1`) VALUES (112, 115, 116, 0, 0, 7, 2, 0, 2, 0);

# 
# Dump completed
