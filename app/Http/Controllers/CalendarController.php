<?php
// Copyright (c) Microsoft Corporation.
// Licensed under the MIT License.

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use App\TokenStore\TokenCache;
use App\TimeZones\TimeZones;

class CalendarController extends Controller
{
  public function calendar()
  {
    $viewData = $this->loadViewData();

    $graph = $this->getGraph();
 

    // Fetch the user's mail
    $messages = $graph->createRequest('GET', 'https://graph.microsoft.com/v1.0/users/flowz@aakronrule.com/messages')
      ->setReturnType(Model\Message::class)
      ->execute();

    $viewData['messages'] = $messages;
    dd($messages);
    return view('calendar', $viewData);
  }

  // <getNewEventFormSnippet>
  public function getNewEventForm()
  {
    $viewData = $this->loadViewData();

    return view('newevent', $viewData);
  }
  // </getNewEventFormSnippet>

  // <createNewEventSnippet>
  public function createNewEvent(Request $request)
  {
    // Validate required fields
    $request->validate([
      'eventSubject' => 'nullable|string',
      'eventAttendees' => 'nullable|string',
      'eventStart' => 'required|date',
      'eventEnd' => 'required|date',
      'eventBody' => 'nullable|string'
    ]);

    $viewData = $this->loadViewData();

    $graph = $this->getGraph();

    // Attendees from form are a semi-colon delimited list of
    // email addresses
    $attendeeAddresses = explode(';', $request->eventAttendees);

    // The Attendee object in Graph is complex, so build the structure
    $attendees = [];
    foreach($attendeeAddresses as $attendeeAddress)
    {
      array_push($attendees, [
        // Add the email address in the emailAddress property
        'emailAddress' => [
          'address' => $attendeeAddress
        ],
        // Set the attendee type to required
        'type' => 'required'
      ]);
    }

    // Build the event
    $newEvent = [
      'subject' => $request->eventSubject,
      'attendees' => $attendees,
      'start' => [
        'dateTime' => $request->eventStart,
        'timeZone' => $viewData['userTimeZone']
      ],
      'end' => [
        'dateTime' => $request->eventEnd,
        'timeZone' => $viewData['userTimeZone']
      ],
      'body' => [
        'content' => $request->eventBody,
        'contentType' => 'text'
      ]
    ];

    // POST /me/events
    $response = $graph->createRequest('POST', '/me/events')
      ->attachBody($newEvent)
      ->setReturnType(Model\Event::class)
      ->execute();

    return redirect('/calendar');
  }
  // </createNewEventSnippet>

  private function getGraph(): Graph
  {
    // Get the access token from the cache
    $tokenCache = new TokenCache();
    $accessToken = $tokenCache->getAccessToken();

    // Create a Graph client
    $graph = new Graph();
    $graph->setAccessToken($accessToken);
    return $graph;
  }
}
