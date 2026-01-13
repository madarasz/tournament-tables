# Purpose
This is project “Kill Team Tables” I’m a tournament organiser for Warhammer 40k Kill Team. During a tournament we have multiple tables set up with different terrain layouts, so our players get to play on a different layout each round. The organising of the tournament, with its score tracking and pairing, is done by the BestCoastPairings.com website, it works well. The only thing missing that its table allocation does not take it into account that each player should play on different tables each round during the tournament.

# Technical Requirements
I want to make a web app that generates new table allocations based on the pairings available on the Best Coast Pairings (BCP) website. Tech stack should be PHP (version 7.4.33 is available) with MySQL.

The frontend framework is not yet decided, suggest something minimalistic 

It would be ideal to have the authorization of the admin done in a super simple manner, strict security is not a requirement. Idea: generate a 16 character length base64 admin token when creating a tournament. Display it and store it as a cookie (3 days retention), with the option that the admin can authenticate by entering the token manually.

# Scenarios
## Tournament creation
Before the tournament, the organiser (admin) should be able create the tournament with these information:
- Best Coast Parings URL (to read pairings from)
- number of tables
- optional: set terrain type for each table (there is a list of terrain types available in the DB)

## Table allocation
During the tournament, the organiser should be able to generate the table allocation for a certain tournament round. The application should read the pairings from BCP. Based on the pairing information, it should generate table numbers for the paired players for the selected round. The requirements for the table number are the following in priority order:
1. table allocation for the first round given by BCP is taken as is
2. a player should not play on a table that it played in previous rounds
3. a player should play on a terrain type it had not played before
4. players with more points should play on a lower table number (top player goes to the first table)

## Table allocation edit and publication
The organiser should be able to edit the table number for a player pair. The organiser should be able to switch two tables. Conflicts should be highlighted (a table is used multiple times or table number requirement 2 or 3 is violated). When the organiser is happy with the table numbers for the round, he can publish the table allocation.

## User access
The users (without any authentication) should be able to view the table allocations published.

