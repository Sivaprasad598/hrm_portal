<?php
/*
 * Lead Team View.
 *   lead_team_view.php?id=<lead id>  one lead's Lead Team (Lead Team, Team Chat, Lead Team Meetings), without the
 *                                    lead page's other tabs
 *   lead_team_view.php               My Lead Teams: the leads the signed-in employee is assigned to
 *
 * Both pages are served by the portal (LeadContactController::teamView / myTeams) behind its login; they check
 * on the server that the employee is the lead's owner or on its team. This file only sends the browser there.
 */
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

header('Location: ' . ($id ? 'public/account/lead-contact/' . $id . '/team-only' : 'public/account/lead-contact/my-teams'), true, 302);
exit;
