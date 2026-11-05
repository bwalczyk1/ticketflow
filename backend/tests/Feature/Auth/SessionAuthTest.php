<?php

it('registers an attendee and starts a session')->todo();
it('normalizes the email to lower case on registration')->todo();
it('rejects a role in the registration body with 400')->todo();
it('rejects a password shorter than 10 or longer than 72 characters')->todo();
it('answers 409 EMAIL_TAKEN for a duplicate email that differs only in letter case')->todo();
it('logs in and returns the current user from GET /api/me')->todo();
it('changes the session id on login')->todo();
it('gives the same 401 INVALID_CREDENTIALS for a wrong email and a wrong password')->todo();
it('compares against a dummy hash when the user does not exist')->todo();
it('invalidates the session on logout')->todo();
it('answers 401 from GET /api/me without a session')->todo();
it('answers 419 CSRF_TOKEN_MISMATCH for an SPA request without a CSRF token')->todo();
