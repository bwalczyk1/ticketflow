<?php

it('creates a draft event owned by the logged in organizer')->todo();
it('rejects organizer_id in the body with 400')->todo();
it('rejects dates without a timezone offset with 400')->todo();
it('lists only the events of the organizer')->todo();
it('updates an event')->todo();
it('deletes an event without sales')->todo();
it('answers 409 when deleting an event with sold tickets')->todo();
it('answers 403 for an event of another organizer')->todo();
it('answers 403 for an attendee')->todo();
it('publishes a draft event with at least one ticket type')->todo();
it('answers 409 INVALID_STATUS_TRANSITION for a forbidden status change')->todo();
it('cancels a published event and dispatches EventCancelled')->todo();
