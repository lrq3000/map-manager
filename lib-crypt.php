<?php
/*
 * Copyright (C) 2011-2013 Larroque Stephen
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the Affero GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/**
 * \description     Cryptographic auxiliary library (portable to any software, standalone)
 */

// Generate or check an obscured hash for a password, using various algorithm (you can choose)
// Note: you must use again this function to check if a password is valid. You can use something like: ( obscure($_POST["password"], $passwordhash) == $passwordhash ) ? print 'ok' : print 'ko';
// Note2: I am NOT the author of this function, but the author is not known. Also, this is an enhanced, non buggy version.
function obscure($password, $salt = null, $algorithm = "whirlpool")
{
	// Get some random salt, or verify a salt.
	// Added by (grosbedo AT gmail DOT com)
	if ($salt == NULL)
	{
	    $salt = hash($algorithm, uniqid(rand(), true));
	}

	// Determine the length of the hash.
	$hash_length = strlen($salt);

	// Determine the length of the password.
	$password_length = strlen($password);

	// Determine the maximum length of password. This is only needed if
	// the user enters a very long password. In any case, the salt will
	// be a maximum of half the end result. The longer the hash, the
	// longer the password/salt can be.
	$password_max_length = $hash_length / 2;

	// Shorten the salt based on the length of the password.
	if ($password_length >= $password_max_length) {
		$salt = substr($salt, 0, $password_max_length);
	} else {
		$salt = substr($salt, 0, $password_length);
	}

	// Determine the length of the salt.
	$salt_length = strlen($salt);

	// Determine the salted hashed password.
	$salted_password = hash($algorithm, $salt . $password);

	// If we add the salt to the hashed password, we would get a hash that
	// is longer than a normally hashed password. We don't want that; it
	// would give away hints to an attacker. Because the password and the
	// length of the password are known, we can just throw away the first
	// couple of characters of the salted password. That way the salt and
	// the salted password together are the same length as a normally
	// hashed password without salt.
	$used_chars = ($hash_length - $salt_length) * -1;
	$final_result = $salt . substr($salted_password, $used_chars);

	return $final_result;
}
?>