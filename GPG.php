<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Crypt_GPG is a package to use GPG from PHP
 *
 * This package provides an object oriented interface to GNU Privacy
 * Guard (GPG). It requires the GPG executable to be on the system.
 *
 * Though GPG can support symmetric-key cryptography, this package is intended
 * only to facilitate public-key cryptography.
 *
 * This file contains the main GPG class. The class in this file lets you
 * encrypt, decrypt, sign and verify data; import and delete keys; and perform
 * other useful GPG tasks.
 *
 * Example usage:
 * <code>
 * <?php
 * // encrypt some data
 * $gpg = new Crypt_GPG();
 * $gpg->addEncryptKey($mySecretKeyId);
 * $encryptedData = $gpg->encrypt($data);
 * ?>
 * </code>
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * This library is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation; either version 2.1 of the
 * License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Nathan Fredrickson <nathan@silverorange.com>
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2005-2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @version   CVS: $Id$
 * @link      http://pear.php.net/package/Crypt_GPG
 * @link      http://pear.php.net/manual/en/package.encryption.crypt-gpg.php
 * @link      http://www.gnupg.org/
 */

/**
 * Signature handler class
 */
require_once 'Crypt/GPG/VerifyStatusHandler.php';

/**
 * Decryption handler class
 */
require_once 'Crypt/GPG/DecryptStatusHandler.php';

/**
 * GPG key class
 */
require_once 'Crypt/GPG/Key.php';

/**
 * GPG sub-key class
 */
require_once 'Crypt/GPG/SubKey.php';

/**
 * GPG user id class
 */
require_once 'Crypt/GPG/UserId.php';

/**
 * GPG process and I/O engine class
 */
require_once 'Crypt/GPG/Engine.php';

/**
 * GPG exception classes
 */
require_once 'Crypt/GPG/Exceptions.php';

// {{{ class Crypt_GPG

/**
 * A class to use GPG from PHP
 *
 * This class provides an object oriented interface to GNU Privacy Guard (GPG).
 *
 * Though GPG can support symmetric-key cryptography, this class is intended
 * only to facilitate public-key cryptography.
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Nathan Fredrickson <nathan@silverorange.com>
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2005-2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 * @link      http://www.gnupg.org/
 */
class Crypt_GPG
{
    // {{{ class error constants

    /**
     * Error code returned when there is no error.
     */
    const ERROR_NONE = 0;

    /**
     * Error code returned when an unknown or unhandled error occurs.
     */
    const ERROR_UNKNOWN = 1;

    /**
     * Error code returned when a bad passphrase is used.
     */
    const ERROR_BAD_PASSPHRASE = 2;

    /**
     * Error code returned when a required passphrase is missing.
     */
    const ERROR_MISSING_PASSPHRASE = 3;

    /**
     * Error code returned when a key that is already in the keyring is
     * imported.
     */
    const ERROR_DUPLICATE_KEY = 4;

    /**
     * Error code returned the required data is missing for an operation.
     *
     * This could be missing key data, missing encrypted data or missing
     * signature data.
     */
    const ERROR_NO_DATA = 5;

    /**
     * Error code returned when an unsigned key is used.
     */
    const ERROR_UNSIGNED_KEY = 6;

    /**
     * Error code returned when a key that is not self-signed is used.
     */
    const ERROR_NOT_SELF_SIGNED = 7;

    /**
     * Error code returned when a public or private key that is not in the
     * keyring is used.
     */
    const ERROR_KEY_NOT_FOUND = 8;

    /**
     * Error code returned when an attempt to delete public key having a
     * private key is made.
     */
    const ERROR_DELETE_PRIVATE_KEY = 9;

    // }}}
    // {{{ class constants for data signing modes

    /**
     * Signing mode for normal signing of data. The signed message will not
     * be readable without special software.
     *
     * This is the default signing mode.
     *
     * @see Crypt_GPG::sign()
     * @see Crypt_GPG::signFile()
     */
    const SIGN_MODE_NORMAL = 1;

    /**
     * Signing mode for clearsigning data. Clearsigned signatures are ASCII
     * armored data and are readable without special software. If the signed
     * message is unencrypted, the message will still be readable. The message
     * text will be in the original encoding.
     *
     * @see Crypt_GPG::sign()
     * @see Crypt_GPG::signFile()
     */
    const SIGN_MODE_CLEAR = 2;

    /**
     * Signing mode for creating a detached signature. When using detached
     * signatures, only the signature data is returned. The original message
     * text may be distributed separately from the signature data. This is
     * useful for miltipart/signed email messages as per
     * {@link http://www.ietf.org/rfc/rfc3156.txt RFC 3156}.
     *
     * @see Crypt_GPG::sign()
     * @see Crypt_GPG::signFile()
     */
    const SIGN_MODE_DETACHED = 3;

    // }}}
    // {{{ class constants for fingerprint formats

    /**
     * No formatting is performed.
     *
     * Example: C3BC615AD9C766E5A85C1F2716D27458B1BBA1C4
     *
     * @see Crypt_GPG::getFingerprint()
     */
    const FORMAT_NONE = 1;

    /**
     * Fingerprint is formatted in the format used by the GnuPG gpg command's
     * default output.
     *
     * Example: C3BC 615A D9C7 66E5 A85C  1F27 16D2 7458 B1BB A1C4
     *
     * @see Crypt_GPG::getFingerprint()
     */
    const FORMAT_CANONICAL = 2;

    /**
     * Fingerprint is formatted in the format used when displaying X.509
     * certificates
     *
     * Example: C3:BC:61:5A:D9:C7:66:E5:A8:5C:1F:27:16:D2:74:58:B1:BB:A1:C4
     *
     * @see Crypt_GPG::getFingerprint()
     */
    const FORMAT_X509 = 3;

    // }}}
    // {{{ protected class properties

    /**
     * Engine used to control the GPG subprocess
     *
     * @var Crypt_GPG_Engine
     */
    protected $engine = null;

    /**
     * Keys used to encrypt
     *
     * The array is of the form:
     * <code>
     * array(
     *   $key_id => array(
     *     'fingerprint' => $fingerprint,
     *     'passphrase'  => null
     *   )
     * );
     * </code>
     *
     * @var array
     * @see Crypt_GPG::addEncryptKey()
     * @see Crypt_GPG::clearEncryptKeys()
     */
    protected $encryptKeys = array();

    /**
     * Keys used to decrypt
     *
     * The array is of the form:
     * <code>
     * array(
     *   $key_id => array(
     *     'fingerprint' => $fingerprint,
     *     'passphrase'  => $passphrase
     *   )
     * );
     * </code>
     *
     * @var array
     * @see Crypt_GPG::addSignKey()
     * @see Crypt_GPG::clearSignKeys()
     */
    protected $signKeys = array();

    /**
     * Keys used to sign
     *
     * The array is of the form:
     * <code>
     * array(
     *   $key_id => array(
     *     'fingerprint' => $fingerprint,
     *     'passphrase'  => $passphrase
     *   )
     * );
     * </code>
     *
     * @var array
     * @see Crypt_GPG::addDecryptKey()
     * @see Crypt_GPG::clearDecryptKeys()
     */
    protected $decryptKeys = array();

    // }}}
    // {{{ __construct()

    /**
     * Creates a new GPG object
     *
     * Available options are:
     *
     * - <code>string  homedir</code>   - the directory where the GPG keyring
     *                                    files are stored. If not specified,
     *                                    Crypt_GPG uses the default of
     *                                    <code>~/.gnupg</code>.
     * - <code>string  gpgBinary</code> - the location of the GPG binary. If not
     *                                    specified, the driver attempts to
     *                                    auto-detect the GPG binary location
     *                                    using a list of known default
     *                                    locations for the current operating
     *                                    system.
     * - <code>boolean debug</code>     - whether or not to use debug mode. When
     *                                    debug mode is on, all communication
     *                                    to and from the GPG subprocess is
     *                                    logged. This can be useful to diagnose
     *                                    errors when using Crypt_GPG.
     *
     * @param array $options optional. An array of options used to create the
     *                       GPG object. All options must be optional and are
     *                       represented as key-value pairs.
     *
     * @throws Crypt_GPG_FileException if the <code>homedir</code> does not
     *         exist and cannot be created. This can happen if
     *         <code>homedir</code> is not specified, Crypt_GPG is run as the
     *         web user, and the web user has no home directory.
     *
     * @throws PEAR_Exception if the provided <code>gpgBinary</code> is invalid,
     *         or if no <code>gpgBinary</code> is provided and no suitable
     *         binary could be found.
     */
    public function __construct(array $options = array())
    {
        $this->engine = new Crypt_GPG_Engine($options);
    }

    // }}}
    // {{{ importKey()

    /**
     * Imports a public or private key into the keyring
     *
     * Keys may be removed from the keyring using
     * {@link Crypt_GPG::deletePublicKey()} or
     * {@link Crypt_GPG::deletePrivateKey()}.
     *
     * @param string $data the key data to be imported.
     *
     * @return array an associative array containing the following elements:
     *               - <code>fingerprint</code>       - the fingerprint of the
     *                                                  imported key,
     *               - <code>public_imported</code>   - the number of public
     *                                                  keys imported,
     *               - <code>public_unchanged</code>  - the number of unchanged
     *                                                  public keys,
     *               - <code>private_imported</code>  - the number of private
     *                                                  keys imported,
     *               - <code>private_unchanged</code> - the number of unchanged
     *                                                  private keys.
     *
     * @throws Crypt_GPG_NoDataException if the key data is missing or if the
     *         data is is not valid key data.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     */
    public function importKey($data)
    {
        return $this->_importKey($data, false);
    }

    // }}}
    // {{{ importKeyFile()

    /**
     * Imports a public or private key file into the keyring
     *
     * Keys may be removed from the keyring using
     * {@link Crypt_GPG::deletePublicKey()} or
     * {@link Crypt_GPG::deletePrivateKey()}.
     *
     * @param string $filename the key file to be imported.
     *
     * @return array an associative array containing the following elements:
     *               - <code>fingerprint</code>       - the fingerprint of the
     *                                                  imported key,
     *               - <code>public_imported</code>   - the number of public
     *                                                  keys imported,
     *               - <code>public_unchanged</code>  - the number of unchanged
     *                                                  public keys,
     *               - <code>private_imported</code>  - the number of private
     *                                                  keys imported,
     *               - <code>private_unchanged</code> - the number of unchanged
     *                                                  private keys.
     *
     * @throws Crypt_GPG_NoDataException if the key data is missing or if the
     *         data is is not valid key data.
     *
     * @throws Crypt_GPG_FileException if the key file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     */
    public function importKeyFile($filename)
    {
        return $this->_importKey($filename, true);
    }

    // }}}
    // {{{ exportPublicKey()

    /**
     * Exports a public key from the keyring
     *
     * The exported key remains on the keyring. To delete the public key, use
     * {@link Crypt_GPG::deletePublicKey()}.
     *
     * If more than one key fingerprint is available for the specified
     * <i>$keyId</i> (for example, if you use a non-unique uid) only the first
     * public key is exported.
     *
     * @param string  $keyId either the full uid of the public key, the email
     *                       part of the uid of the public key or the key id of
     *                       the public key. For example,
     *                       "Test User (example) <test@example.com>",
     *                       "test@example.com" or a hexadecimal string.
     * @param boolean $armor optional. If true, ASCII armored data is returned;
     *                       otherwise, binary data is returned. Defaults to
     *                       true.
     *
     * @return string the public key data.
     *
     * @throws Crypt_GPG_KeyNotFoundException if a public key with the given
     *         <i>$keyId</i> is not found.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     */
    public function exportPublicKey($keyId, $armor = true)
    {
        $fingerprint = $this->getFingerprint($keyId);

        if ($fingerprint === null) {
            throw new Crypt_GPG_KeyNotFoundException(
                'Public key not found: ' . $keyId,
                Crypt_GPG::ERROR_KEY_NOT_FOUND, $keyId);
        }

        $keyData   = '';
        $operation = '--export ' . escapeshellarg($fingerprint);
        $arguments = ($armor) ? array('--armor') : array();

        $this->engine->reset();
        $this->engine->setOutput($keyData);
        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();

        $code = $this->engine->getErrorCode();

        if ($code !== Crypt_GPG::ERROR_NONE) {
            throw new Crypt_GPG_Exception(
                'Unknown error exporting public key.', $code);
        }

        return $keyData;
    }

    // }}}
    // {{{ deletePublicKey()

    /**
     * Deletes a public key from the keyring
     *
     * If more than one key fingerprint is available for the specified
     * <i>$keyId</i> (for example, if you use a non-unique uid) only the first
     * public key is deleted.
     *
     * The private key must be deleted first or an exception will be thrown.
     * See {@link Crypt_GPG::deletePrivateKey()}.
     *
     * @param string $keyId either the full uid of the public key, the email
     *                      part of the uid of the public key or the key id of
     *                      the public key. For example,
     *                      "Test User (example) <test@example.com>",
     *                      "test@example.com" or a hexadecimal string.
     *
     * @return void
     *
     * @throws Crypt_GPG_KeyNotFoundException if a public key with the given
     *         <i>$keyId</i> is not found.
     *
     * @throws Crypt_GPG_DeletePrivateKeyException if the specified public key
     *         has an associated private key on the keyring. The private key
     *         must be deleted first.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     */
    public function deletePublicKey($keyId)
    {
        $fingerprint = $this->getFingerprint($keyId);

        if ($fingerprint === null) {
            throw new Crypt_GPG_KeyNotFoundException(
                'Public key not found: ' . $keyId,
                Crypt_GPG::ERROR_KEY_NOT_FOUND, $keyId);
        }

        $operation = '--delete-key ' . escapeshellarg($fingerprint);
        $arguments = array(
            '--batch',
            '--yes'
        );

        $this->engine->reset();
        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();

        $code = $this->engine->getErrorCode();

        switch ($code) {
        case Crypt_GPG::ERROR_NONE:
            break;
        case Crypt_GPG::ERROR_DELETE_PRIVATE_KEY:
            throw new Crypt_GPG_DeletePrivateKeyException(
                'Private key must be deleted before public key can be ' .
                'deleted.', $code, $keyId);
        default:
            throw new Crypt_GPG_Exception(
                'Unknown error deleting public key.', $code);
        }
    }

    // }}}
    // {{{ deletePrivateKey()

    /**
     * Deletes a private key from the keyring
     *
     * If more than one key fingerprint is available for the specified
     * <i>$keyId</i> (for example, if you use a non-unique uid) only the first
     * private key is deleted.
     *
     * Calls GPG with the --delete-secret-key command.
     *
     * @param string $keyId either the full uid of the private key, the email
     *                      part of the uid of the private key or the key id of
     *                      the private key. For example,
     *                      "Test User (example) <test@example.com>",
     *                      "test@example.com" or a hexadecimal string.
     *
     * @return void
     *
     * @throws Crypt_GPG_KeyNotFoundException if a private key with the given
     *         <i>$keyId</i> is not found.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     */
    public function deletePrivateKey($keyId)
    {
        $fingerprint = $this->getFingerprint($keyId);

        if ($fingerprint === null) {
            throw new Crypt_GPG_KeyNotFoundException(
                'Private key not found: ' . $keyId,
                Crypt_GPG::ERROR_KEY_NOT_FOUND, $keyId);
        }

        $operation = '--delete-secret-key ' . escapeshellarg($fingerprint);
        $arguments = array(
            '--batch',
            '--yes'
        );

        $this->engine->reset();
        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();

        $code = $this->engine->getErrorCode();

        switch ($code) {
        case Crypt_GPG::ERROR_NONE:
            break;
        case Crypt_GPG::ERROR_KEY_NOT_FOUND:
            throw new Crypt_GPG_KeyNotFoundException(
                'Private key not found: ' . $keyId,
                $code, $keyId);
        default:
            throw new Crypt_GPG_Exception(
                'Unknown error deleting private key.', $code);
        }
    }

    // }}}
    // {{{ getKeys()

    /**
     * Gets the available keys in the keyring
     *
     * Calls GPG with the --list-keys command and grabs keys. See the first
     * section of doc/DETAILS in the
     * {@link http://www.gnupg.org/download/ GPG package} for a detailed
     * description of how the GPG command output is parsed.
     *
     * @param string $keyId optional. Only keys with that match the specified
     *                      pattern are returned. The pattern may be part of
     *                      a user id, a key id or a key fingerprint. If not
     *                      specified, all keys are returned.
     *
     * @return array an array of {@link Crypt_GPG_Key} objects. If no keys
     *               match the specified <i>$keyId</i> an empty array is
     *               returned.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     *
     * @see Crypt_GPG_Key
     */
    public function getKeys($keyId = '')
    {
        // get private key fingerprints
        if ($keyId == '') {
            $operation = '--list-secret-keys';
        } else {
            $operation = '--list-secret-keys ' . escapeshellarg($keyId);
        }

        $arguments = array(
            '--with-colons',
            '--with-fingerprint',
            '--with-fingerprint',
            '--fixed-list-mode'
        );

        $output = '';

        $this->engine->reset();
        $this->engine->setOutput($output);
        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();

        $code = $this->engine->getErrorCode();

        switch ($code) {
        case Crypt_GPG::ERROR_NONE:
        case Crypt_GPG::ERROR_KEY_NOT_FOUND:
            // ignore not found key errors
            break;
        default:
            throw new Crypt_GPG_Exception(
                'Unknown error getting keys.', $code);
        }

        $privateKeyFingerprints = array();

        $lines = explode(PHP_EOL, $output);
        foreach ($lines as $line) {
            $lineExp = explode(':', $line);
            if ($lineExp[0] == 'fpr') {
                $privateKeyFingerprints[] = $lineExp[9];
            }
        }

        // get public keys
        if ($keyId == '') {
            $operation = '--list-public-keys';
        } else {
            $operation = '--list-public-keys ' . escapeshellarg($keyId);
        }

        $output = '';

        $this->engine->reset();
        $this->engine->setOutput($output);
        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();

        $code = $this->engine->getErrorCode();

        switch ($code) {
        case Crypt_GPG::ERROR_NONE:
        case Crypt_GPG::ERROR_KEY_NOT_FOUND:
            // ignore not found key errors
            break;
        default:
            throw new Crypt_GPG_Exception(
                'Unknown error getting keys.', $code);
        }

        $keys = array();

        $key    = null; // current key
        $subKey = null; // current sub-key

        $lines = explode(PHP_EOL, $output);
        foreach ($lines as $line) {
            $lineExp = explode(':', $line);

            if ($lineExp[0] == 'pub') {

                // new primary key means last key should be added to the array
                if ($key !== null) {
                    $keys[] = $key;
                }

                $key = new Crypt_GPG_Key();

                $subKey = Crypt_GPG_SubKey::parse($line);
                $key->addSubKey($subKey);

            } elseif ($lineExp[0] == 'sub') {

                $subKey = Crypt_GPG_SubKey::parse($line);
                $key->addSubKey($subKey);

            } elseif ($lineExp[0] == 'fpr') {

                $fingerprint = $lineExp[9];

                // set current sub-key fingerprint
                $subKey->setFingerprint($fingerprint);

                // if private key exists, set has private to true
                if (in_array($fingerprint, $privateKeyFingerprints)) {
                    $subKey->setHasPrivate(true);
                }

            } elseif ($lineExp[0] == 'uid') {

                $string = stripcslashes($lineExp[9]); // as per documentation
                $key->addUserId(Crypt_GPG_UserId::parse($string));

            }
        }

        // add last key
        if ($key !== null) {
            $keys[] = $key;
        }

        return $keys;
    }

    // }}}
    // {{{ getFingerprint()

    /**
     * Gets a key fingerprint from the keyring
     *
     * If more than one key fingerprint is available (for example, if you use
     * a non-unique user id) only the first key fingerprint is returned.
     *
     * Calls the GPG --list-keys command with the --with-fingerprint option to
     * retrieve a public key fingerprint.
     *
     * @param string  $keyId  either the full user id of the key, the email
     *                        part of the user id of the key, or the key id of
     *                        the key. For example,
     *                        "Test User (example) <test@example.com>",
     *                        "test@example.com" or a hexadecimal string.
     * @param integer $format optional. How the fingerprint should be formatted.
     *                        Use {@link Crypt_GPG::FORMAT_X509} for X.509
     *                        certificate format,
     *                        {@link Crypt_GPG::FORMAT_CANONICAL} for the format
     *                        used by GnuPG output and
     *                        {@link Crypt_GPG::FORMAT_NONE} for no formatting.
     *                        Defaults to <code>Crypt_GPG::FORMAT_NONE</code>.
     *
     * @return string the fingerprint of the key, or null if no fingerprint
     *                is found for the given <i>$keyId</i>.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     */
    public function getFingerprint($keyId, $format = Crypt_GPG::FORMAT_NONE)
    {
        $output    = '';
        $operation = '--list-keys ' . escapeshellarg($keyId);
        $arguments = array(
            '--with-colons',
            '--with-fingerprint'
        );

        $this->engine->reset();
        $this->engine->setOutput($output);
        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();

        $code = $this->engine->getErrorCode();

        switch ($code) {
        case Crypt_GPG::ERROR_NONE:
        case Crypt_GPG::ERROR_KEY_NOT_FOUND:
            // ignore not found key errors
            break;
        default:
            throw new Crypt_GPG_Exception(
                'Unknown error getting key fingerprint.', $code);
        }

        $fingerprint = null;

        $lines = explode(PHP_EOL, $output);
        foreach ($lines as $line) {
            if (substr($line, 0, 3) == 'fpr') {
                $lineExp     = explode(':', $line);
                $fingerprint = $lineExp[9];

                switch ($format) {
                case Crypt_GPG::FORMAT_CANONICAL:
                    $fingerprintExp = str_split($fingerprint, 4);
                    $format         = '%s %s %s %s %s  %s %s %s %s %s';
                    $fingerprint    = vsprintf($format, $fingerprintExp);
                    break;

                case Crypt_GPG::FORMAT_X509:
                    $fingerprintExp = str_split($fingerprint, 2);
                    $fingerprint    = implode(':', $fingerprintExp);
                    break;
                }

                break;
            }
        }

        return $fingerprint;
    }

    // }}}
    // {{{ encrypt()

    /**
     * Encrypts string data
     *
     * Data is ASCII armored by default but may optionally be returned as
     * binary.
     *
     * @param string  $data  the data to be encrypted.
     * @param boolean $armor optional. If true, ASCII armored data is returned;
     *                       otherwise, binary data is returned. Defaults to
     *                       true.
     *
     * @return string the encrypted data.
     *
     * @throws Crypt_GPG_KeyNotFoundException if no encryption key is specified.
     *         See {@link Crypt_GPG::addEncryptKey()}.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     *
     * @sensitive $data
     */
    public function encrypt($data, $armor = true)
    {
        return $this->_encrypt($data, false, null, $armor);
    }

    // }}}
    // {{{ encryptFile()

    /**
     * Encrypts a file
     *
     * Encrypted data is ASCII armored by default but may optionally be saved
     * as binary.
     *
     * @param string  $filename      the filename of the file to encrypt.
     * @param string  $encryptedFile optional. The filename of the file in
     *                               which to store the encrypted data. If null
     *                               or unspecified, the encrypted data is
     *                               returned as a string.
     * @param boolean $armor         optional. If true, ASCII armored data is
     *                               returned; otherwise, binary data is
     *                               returned. Defaults to true.
     *
     * @return void|string if the <code>$encryptedFile</code> parameter is null,
     *                     a string containing the encrypted data is returned.
     *
     * @throws Crypt_GPG_KeyNotFoundException if no encryption key is specified.
     *         See {@link Crypt_GPG::addEncryptKey()}.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     */
    public function encryptFile($filename, $encryptedFile = null, $armor = true)
    {
        return $this->_encrypt($filename, true, $encryptedFile, $armor);
    }

    // }}}
    // {{{ decrypt()

    /**
     * Decrypts string data
     *
     * This method assumes the required private key is available in the keyring
     * and throws an exception if the private key is not available. To add a
     * private key to the keyring, use the {@link Crypt_GPG::importKey()} or
     * {@link Crypt_GPG::importKeyFile()} methods.
     *
     * @param string $encryptedData the data to be decrypted.
     *
     * @return string the decrypted data.
     *
     * @throws Crypt_GPG_KeyNotFoundException if the private key needed to
     *         decrypt the data is not in the user's keyring.
     *
     * @throws Crypt_GPG_NoDataException if specified data does not contain
     *         GPG encrypted data.
     *
     * @throws Crypt_GPG_BadPassphraseException if a required passphrase is
     *         incorrect or if a required passphrase is not specified. See
     *         {@link Crypt_GPG::addDecryptKey()}.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     */
    public function decrypt($encryptedData)
    {
        return $this->_decrypt($encryptedData, false, null);
    }

    // }}}
    // {{{ decryptFile()

    /**
     * Decrypts a file
     *
     * This method assumes the required private key is available in the keyring
     * and throws an exception if the private key is not available. To add a
     * private key to the keyring, use the {@link Crypt_GPG::importKey()} or
     * {@link Crypt_GPG::importKeyFile()} methods.
     *
     * @param string $encryptedFile the name of the encrypted file data to
     *                              decrypt.
     * @param string $decryptedFile optional. The name of the file to which the
     *                              decrypted data should be written. If null
     *                              or unspecified, the decrypted data is
     *                              returned as a string.
     *
     * @return void|string if the <code>$decryptedFile</code> parameter is null,
     *                     a string containing the decrypted data is returned.
     *
     * @throws Crypt_GPG_KeyNotFoundException if the private key needed to
     *         decrypt the data is not in the user's keyring.
     *
     * @throws Crypt_GPG_NoDataException if specified data does not contain
     *         GPG encrypted data.
     *
     * @throws Crypt_GPG_BadPassphraseException if a required passphrase is
     *         incorrect or if a required passphrase is not specified. See
     *         {@link Crypt_GPG::addDecryptKey()}.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     */
    public function decryptFile($encryptedFile, $decryptedFile = null)
    {
        return $this->_decrypt($encryptedFile, true, $decryptedFile);
    }

    // }}}
    // {{{ sign()

    /**
     * Signs data
     *
     * Data may be signed using any one of the three available signing modes:
     * - {@link Crypt_GPG::SIGN_MODE_NORMAL}
     * - {@link Crypt_GPG::SIGN_MODE_CLEAR}
     * - {@link Crypt_GPG::SIGN_MODE_DETACHED}
     *
     * @param string  $data  the data to be signed.
     * @param boolean $mode  optional. The data signing mode to use. Should
     *                       be one of {@link Crypt_GPG::SIGN_MODE_NORMAL},
     *                       {@link Crypt_GPG::SIGN_MODE_CLEAR} or
     *                       {@link Crypt_GPG::SIGN_MODE_DETACHED}. If not
     *                       specified, defaults to
     *                       <code>Crypt_GPG::SIGN_MODE_NORMAL</code>.
     * @param boolean $armor optional. If true, ASCII armored data is returned;
     *                       otherwise, binary data is returned. Defaults to
     *                       true. This has no effect if the mode
     *                       <code>Crypt_GPG::SIGN_MODE_CLEAR</code> is used.
     *
     * @return string the signed data, or the signature data if a detached
     *                signature is requested.
     *
     * @throws Crypt_GPG_KeyNotFoundException if no signing key is specified.
     *         See {@link Crypt_GPG::addSignKey()}.
     *
     * @throws Crypt_GPG_BadPassphraseException if a specified passphrase is
     *         incorrect or if a required passphrase is not specified.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     */
    public function sign($data, $mode = Crypt_GPG::SIGN_MODE_NORMAL,
        $armor = true)
    {
        return $this->_sign($data, false, null, $mode, $armor);
    }

    // }}}
    // {{{ signFile()

    /**
     * Signs a file
     *
     * The file may be signed using any one of the three available signing
     * modes:
     * - {@link Crypt_GPG::SIGN_MODE_NORMAL}
     * - {@link Crypt_GPG::SIGN_MODE_CLEAR}
     * - {@link Crypt_GPG::SIGN_MODE_DETACHED}
     *
     * @param string  $filename   the data to be signed.
     * @param string  $signedFile optional. The name of the file in which the
     *                            signed data should be stored. If null or
     *                            unspecified, the signed data is returned as a
     *                            string.
     * @param boolean $mode       optional. The data signing mode to use. Should
     *                            be one of {@link Crypt_GPG::SIGN_MODE_NORMAL},
     *                            {@link Crypt_GPG::SIGN_MODE_CLEAR} or
     *                            {@link Crypt_GPG::SIGN_MODE_DETACHED}. If not
     *                            specified, defaults to
     *                            <code>Crypt_GPG::SIGN_MODE_NORMAL</code>.
     * @param boolean $armor      optional. If true, ASCII armored data is
     *                            returned; otherwise, binary data is returned.
     *                            Defaults to true. This has no effect if the
     *                            mode <code>Crypt_GPG::SIGN_MODE_CLEAR</code>
     *                            is used.
     *
     * @return void|string if the <code>$signedFile</code> parameter is null,
     *                     a string containing the signed data (or the
     *                     signature data if a detached signature is requested)
     *                     is returned.
     *
     * @throws Crypt_GPG_KeyNotFoundException if no signing key is specified.
     *         See {@link Crypt_GPG::addSignKey()}.
     *
     * @throws Crypt_GPG_BadPassphraseException if a specified passphrase is
     *         incorrect or if a required passphrase is not specified.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     */
    public function signFile($filename, $signedFile = null,
        $mode = Crypt_GPG::SIGN_MODE_NORMAL, $armor = true)
    {
        return $this->_sign($filename, true, $signedFile, $mode, $armor);
    }

    // }}}
    // {{{ verify()

    /**
     * Verifies signed data
     *
     * The {@link Crypt_GPG::decrypt()} method may be used to get the original
     * message if the signed data is not clearsigned and does not use a
     * detached signature.
     *
     * @param string $signedData the signed data to be verified.
     * @param string $signature  optional. If verifying data signed using a
     *                           detached signature, this must be the detached
     *                           signature data. The data that was signed is
     *                           specified in <code>$signedData</code>.
     *
     * @return array an array of {@link Crypt_GPG_Signature} objects for the
     *               signed data. For each signature that is valid, the
     *               {@link Crypt_GPG_Signature::isValid()} will return true.
     *
     * @throws Crypt_GPG_NoDataException if the provided data is not signed
     *         data.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     *
     * @see Crypt_GPG_Signature
     */
    public function verify($signedData, $signature = '')
    {
        return $this->_verify($signedData, false, $signature);
    }

    // }}}
    // {{{ verifyFile()

    /**
     * Verifies a signed file
     *
     * The {@link Crypt_GPG::decryptFile()} method may be used to get the
     * original message if the signed data is not clearsigned and does not use
     * a detached signature.
     *
     * @param string $filename  the signed file to be verified.
     * @param string $signature optional. If verifying a file signed using a
     *                          detached signature, this must be the detached
     *                          signature data. The file that was signed is
     *                          specified in <code>$filename</code>.
     *
     * @return array an array of {@link Crypt_GPG_Signature} objects for the
     *               signed data. For each signature that is valid, the
     *               {@link Crypt_GPG_Signature::isValid()} will return true.
     *
     * @throws Crypt_GPG_NoDataException if the provided data is not signed
     *         data.
     *
     * @throws Crypt_GPG_FileException if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     *
     * @see Crypt_GPG_Signature
     */
    public function verifyFile($filename, $signature = '')
    {
        return $this->_verify($filename, true, $signature);
    }

    // }}}
    // {{{ addDecryptKey()

    /**
     * Adds a key to use for decryption
     *
     * @param mixed  $key        the key to use. This may be a key identifier,
     *                           user id, fingerprint, {@link Crypt_GPG_Key} or
     *                           {@link Crypt_GPG_SubKey}. The key must be able
     *                           to encrypt.
     * @param string $passphrase optional. The passphrase of the key required
     *                           for decryption.
     *
     * @return void
     *
     * @see Crypt_GPG::decrypt()
     * @see Crypt_GPG::clearDecryptKeys()
     * @see Crypt_GPG::handleDecryptStatus()
     * @see Crypt_GPG::_addKey()
     *
     * @sensitive $passphrase
     */
    public function addDecryptKey($key, $passphrase = null)
    {
        $this->_addKey($this->decryptKeys, true, false, $key, $passphrase);
    }

    // }}}
    // {{{ addEncryptKey()

    /**
     * Adds a key to use for encryption
     *
     * @param mixed $key the key to use. This may be a key identifier, user id
     *                   user id, fingerprint, {@link Crypt_GPG_Key} or
     *                   {@link Crypt_GPG_SubKey}. The key must be able to
     *                   encrypt.
     *
     * @return void
     *
     * @see Crypt_GPG::encrypt()
     * @see Crypt_GPG::clearEncryptKeys()
     * @see Crypt_GPG::_addKey()
     */
    public function addEncryptKey($key)
    {
        $this->_addKey($this->encryptKeys, true, false, $key);
    }

    // }}}
    // {{{ addSignKey()

    /**
     * Adds a key to use for signing
     *
     * @param mixed  $key        the key to use. This may be a key identifier,
     *                           user id, fingerprint, {@link Crypt_GPG_Key} or
     *                           {@link Crypt_GPG_SubKey}. The key must be able
     *                           to sign.
     * @param string $passphrase optional. The passphrase of the key required
     *                           for signing.
     *
     * @return void
     *
     * @see Crypt_GPG::decrypt()
     * @see Crypt_GPG::clearSignKeys()
     * @see Crypt_GPG::handleSignStatus()
     * @see Crypt_GPG::_addKey()
     *
     * @sensitive $passphrase
     */
    public function addSignKey($key, $passphrase = null)
    {
        $this->_addKey($this->signKeys, false, true, $key, $passphrase);
    }

    // }}}
    // {{{ clearDecryptKeys()

    /**
     * Clears all decryption keys
     *
     * @return void
     *
     * @see Crypt_GPG::decrypt()
     * @see Crypt_GPG::addDecryptKey()
     */
    public function clearDecryptKeys()
    {
        $this->decryptKeys = array();
    }

    // }}}
    // {{{ clearEncryptKeys()

    /**
     * Clears all encryption keys
     *
     * @return void
     *
     * @see Crypt_GPG::encrypt()
     * @see Crypt_GPG::addEncryptKey()
     */
    public function clearEncryptKeys()
    {
        $this->encryptKeys = array();
    }

    // }}}
    // {{{ clearSignKeys()

    /**
     * Clears all signing keys
     *
     * @return void
     *
     * @see Crypt_GPG::sign()
     * @see Crypt_GPG::addSignKey()
     */
    public function clearSignKeys()
    {
        $this->signKeys = array();
    }

    // }}}
    // {{{ handleSignStatus()

    /**
     * Handles the status output from GPG for the sign operation
     *
     * This method is responsible for sending the passphrase commands when
     * required by the {@link Crypt_GPG::sign()} method. See
     * <strong>doc/DETAILS</strong> in the
     * {@link http://www.gnupg.org/download/ GPG distribution} for detailed
     * info on GPG's status output.
     *
     * @param string $line the status line to handle.
     *
     * @return void
     *
     * @see Crypt_GPG::sign()
     */
    public function handleSignStatus($line)
    {
        $tokens = explode(' ', $line);
        switch ($tokens[0]) {
        case 'NEED_PASSPHRASE':
            $subKeyId = $tokens[1];
            if (array_key_exists($subKeyId, $this->signKeys)) {
                $passphrase = $this->signKeys[$subKeyId]['passphrase'];
                $this->engine->sendCommand($passphrase);
            } else {
                $this->engine->sendCommand('');
            }
            break;
        }
    }

    // }}}
    // {{{ handleImportKeyStatus()

    /**
     * Handles the status output from GPG for the import operation
     *
     * This method is responsible for building the result array that is
     * returned from the {@link Crypt_GPG::importKey()} method. See
     * <strong>doc/DETAILS</strong> in the
     * {@link http://www.gnupg.org/download/ GPG distribution} for detailed
     * info on GPG's status output.
     *
     * @param string $line    the status line to handle.
     * @param array  &$result the current result array being processed.
     *
     * @return void
     *
     * @see Crypt_GPG::import()
     * @see Crypt_GPG_Engine::addStatusHandler()
     */
    public function handleImportKeyStatus($line, array &$result)
    {
        $tokens = explode(' ', $line);
        switch ($tokens[0]) {
        case 'IMPORT_OK':
            $result['fingerprint'] = $tokens[2];
            break;

        case 'IMPORT_RES':
            $result['public_imported']   = intval($tokens[3]);
            $result['public_unchanged']  = intval($tokens[5]);
            $result['private_imported']  = intval($tokens[11]);
            $result['private_unchanged'] = intval($tokens[12]);
            break;
        }
    }

    // }}}
    // {{{ _addKey()

    /**
     * Adds a key to one of the internal key arrays
     *
     * This handles resolving full key objects from the provided
     * <code>$key</code> value.
     *
     * @param array   &$array     the array to which the key should be added.
     * @param boolean $encrypt    whether or not the key must be able to
     *                            encrypt.
     * @param boolean $sign       whether or not the key must be able to sign.
     * @param mixed   $key        the key to add. This may be a key identifier,
     *                            user id, fingerprint, {@link Crypt_GPG_Key} or
     *                            {@link Crypt_GPG_SubKey}.
     * @param string  $passphrase optional. The passphrase associated with the
     *                            key.
     *
     * @return void
     *
     * @sensitive $passphrase
     */
    private function _addKey(array &$array, $encrypt, $sign, $key,
        $passphrase = null)
    {
        if (is_scalar($key)) {
            $keys = $this->getKeys($key);
            if (count($keys) == 0) {
                throw new Crypt_GPG_KeyNotFoundException('Key not found.');
            }
            $key = $keys[0];
        }

        if ($key instanceof Crypt_GPG_Key) {
            if ($encrypt && !$key->canEncrypt()) {
                throw new InvalidArgumentException('Key cannot encrypt.');
            }

            if ($sign && !$key->canSign()) {
                throw new InvalidArgumentException('Key cannot sign.');
            }

            foreach ($key->getSubKeys() as $subKey) {
                $canEncrypt = $subKey->canEncrypt();
                $canSign    = $subKey->canSign();
                if (   ($encrypt && $sign && $canEncrypt && $canSign)
                    || ($encrypt && !$sign && $canEncrypt)
                    || (!$encrypt && $sign && $canSign)
                ) {
                    $key = $subKey;
                    break;
                }
            }
        }

        if (!($key instanceof Crypt_GPG_SubKey)) {
            throw new InvalidArgumentException('Key is not recognized format.');
        }

        if ($encrypt && !$key->canEncrypt()) {
            throw new InvalidArgumentException('Key cannot encrypt.');
        }

        if ($sign && !$key->canSign()) {
            throw new InvalidArgumentException('Key cannot sign.');
        }

        $array[$key->getId()] = array(
            'fingerprint' => $key->getFingerprint(),
            'passphrase'  => $passphrase
        );
    }

    // }}}
    // {{{ _importKey()

    /**
     * Imports a public or private key into the keyring
     *
     * @param string  $key    the key to be imported.
     * @param boolean $isFile whether or not the input is a filename.
     *
     * @return array an associative array containing the following elements:
     *               - <code>fingerprint</code>       - the fingerprint of the
     *                                                  imported key,
     *               - <code>public_imported</code>   - the number of public
     *                                                  keys imported,
     *               - <code>public_unchanged</code>  - the number of unchanged
     *                                                  public keys,
     *               - <code>private_imported</code>  - the number of private
     *                                                  keys imported,
     *               - <code>private_unchanged</code> - the number of unchanged
     *                                                  private keys.
     *
     * @throws Crypt_GPG_NoDataException if the key data is missing or if the
     *         data is is not valid key data.
     *
     * @throws Crypt_GPG_FileException if the key file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     */
    private function _importKey($key, $isFile)
    {
        $result = array();

        if ($isFile) {
            $input = @fopen($key, 'rb');
            if ($input === false) {
                throw new Crypt_GPG_FileException('Could not open key file "' .
                    $key . '" for importing.', 0, $key);
            }
        } else {
            $input = strval($key);
            if ($input == '') {
                throw new Crypt_GPG_NoDataException(
                    'No valid GPG key data found.', Crypt_GPG::ERROR_NO_DATA);
            }
        }

        $this->engine->reset();
        $this->engine->addStatusHandler(array($this, 'handleImportKeyStatus'),
            array(&$result));

        $this->engine->setOperation('--import');
        $this->engine->setInput($input);
        $this->engine->run();

        if ($isFile) {
            fclose($input);
        }

        $code = $this->engine->getErrorCode();

        switch ($code) {
        case Crypt_GPG::ERROR_DUPLICATE_KEY:
        case Crypt_GPG::ERROR_NONE:
            // ignore duplicate key import errors
            break;
        case Crypt_GPG::ERROR_NO_DATA:
            throw new Crypt_GPG_NoDataException(
                'No valid GPG key data found.', $code);
        default:
            throw new Crypt_GPG_Exception(
                'Unknown error importing GPG key.', $code);
        }

        return $result;
    }

    // }}}
    // {{{ _encrypt()

    /**
     * Encrypts data
     *
     * @param string  $data       the data to encrypt.
     * @param boolean $isFile     whether or not the data is a filename.
     * @param string  $outputFile the filename of the file in which to store
     *                            the encrypted data. If null, the encrypted
     *                            data is returned as a string.
     * @param boolean $armor      if true, ASCII armored data is returned;
     *                            otherwise, binary data is returned.
     *
     * @return void|string if the <code>$outputFile</code> parameter is null,
     *                     a string containing the encrypted data is returned.
     *
     * @throws Crypt_GPG_KeyNotFoundException if no encryption key is specified.
     *         See {@link Crypt_GPG::addEncryptKey()}.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     */
    private function _encrypt($data, $isFile, $outputFile, $armor)
    {
        if (count($this->encryptKeys) === 0) {
            throw new Crypt_GPG_KeyNotFoundException(
                'No encryption keys specified.');
        }

        if ($isFile) {
            $input = @fopen($data, 'rb');
            if ($input === false) {
                throw new Crypt_GPG_FileException('Could not open input file "' .
                    $data . '" for encryption.', 0, $data);
            }
        } else {
            $input = strval($data);
        }

        if ($outputFile === null) {
            $output = '';
        } else {
            $output = @fopen($outputFile, 'wb');
            if ($output === false) {
                if ($isFile) {
                    fclose($input);
                }
                throw new Crypt_GPG_FileException('Could not open output ' .
                    'file "' . $outputFile . '" for storing encrypted data.',
                    0, $outputFile);
            }
        }

        $arguments = ($armor) ? array('--armor') : array();
        foreach ($this->encryptKeys as $key) {
            $arguments[] = '--recipient ' . escapeshellarg($key['fingerprint']);
        }

        $this->engine->reset();
        $this->engine->setInput($input);
        $this->engine->setOutput($output);
        $this->engine->setOperation('--encrypt', $arguments);
        $this->engine->run();

        if ($isFile) {
            fclose($input);
        }

        if ($outputFile !== null) {
            fclose($output);
        }

        $code = $this->engine->getErrorCode();

        if ($code !== Crypt_GPG::ERROR_NONE) {
            throw new Crypt_GPG_Exception(
                'Unknown error encrypting data.', $code);
        }

        if ($outputFile === null) {
            return $output;
        }
    }

    // }}}
    // {{{ _decrypt()

    /**
     * Decrypts data
     *
     * @param string  $data       the data to be decrypted.
     * @param boolean $isFile     whether or not the data is a filename.
     * @param string  $outputFile the name of the file to which the decrypted
     *                            data should be written. If null, the decrypted
     *                            data is returned as a string.
     *
     * @return void|string if the <code>$outputFile</code> parameter is null,
     *                     a string containing the decrypted data is returned.
     *
     * @throws Crypt_GPG_KeyNotFoundException if the private key needed to
     *         decrypt the data is not in the user's keyring.
     *
     * @throws Crypt_GPG_NoDataException if specified data does not contain
     *         GPG encrypted data.
     *
     * @throws Crypt_GPG_BadPassphraseException if a required passphrase is
     *         incorrect or if a required passphrase is not specified. See
     *         {@link Crypt_GPG::addDecryptKey()}.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     */
    private function _decrypt($data, $isFile, $outputFile)
    {
        if ($isFile) {
            $input = @fopen($data, 'rb');
            if ($input === false) {
                throw new Crypt_GPG_FileException('Could not open input file "' .
                    $data . '" for decryption.', 0, $data);
            }
        } else {
            $input = strval($data);
            if ($input == '') {
                throw new Crypt_GPG_NoDataException(
                    'Cannot decrypt data. No PGP encrypted data was found in '.
                    'the provided data.', Crypt_GPG::ERROR_NO_DATA);
            }
        }

        if ($outputFile === null) {
            $output = '';
        } else {
            $output = @fopen($outputFile, 'wb');
            if ($output === false) {
                if ($isFile) {
                    fclose($input);
                }
                throw new Crypt_GPG_FileException('Could not open output ' .
                    'file "' . $outputFile . '" for storing decrypted data.',
                    0, $outputFile);
            }
        }

        $handler = new Crypt_GPG_DecryptStatusHandler($this->engine,
            $this->decryptKeys);

        $this->engine->reset();
        $this->engine->addStatusHandler(array($handler, 'handle'));
        $this->engine->setOperation('--decrypt');
        $this->engine->setInput($input);
        $this->engine->setOutput($output);
        $this->engine->run();

        if ($isFile) {
            fclose($input);
        }

        if ($outputFile !== null) {
            fclose($output);
        }

        // if there was any problem decrypting the data, the handler will
        // deal with it here.
        $handler->throwException();

        if ($outputFile === null) {
            return $output;
        }
    }

    // }}}
    // {{{ _sign()

    /**
     * Signs data
     *
     * @param string  $data       the data to be signed.
     * @param boolean $isFile     whether or not the data is a filename.
     * @param string  $outputFile the name of the file in which the signed data
     *                            should be stored. If null, the signed data is
     *                            returned as a string.
     * @param boolean $mode       the data signing mode to use. Should be one of
     *                            {@link Crypt_GPG::SIGN_MODE_NORMAL},
     *                            {@link Crypt_GPG::SIGN_MODE_CLEAR} or
     *                            {@link Crypt_GPG::SIGN_MODE_DETACHED}.
     * @param boolean $armor      if true, ASCII armored data is returned;
     *                            otherwise, binary data is returned. This has
     *                            no effect if the mode
     *                            <code>Crypt_GPG::SIGN_MODE_CLEAR</code> is
     *                            used.
     *
     * @return void|string if the <code>$outputFile</code> parameter is null,
     *                     a string containing the signed data (or the
     *                     signature data if a detached signature is requested)
     *                     is returned.
     *
     * @throws Crypt_GPG_KeyNotFoundException if no signing key is specified.
     *         See {@link Crypt_GPG::addSignKey()}.
     *
     * @throws Crypt_GPG_BadPassphraseException if a specified passphrase is
     *         incorrect or if a required passphrase is not specified.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     */
    private function _sign($data, $isFile, $outputFile, $mode, $armor)
    {
        if (count($this->signKeys) === 0) {
            throw new Crypt_GPG_KeyNotFoundException(
                'No signing keys specified.');
        }

        if ($isFile) {
            $input = @fopen($data, 'rb');
            if ($input === false) {
                throw new Crypt_GPG_FileException('Could not open input ' .
                    'file "' . $data . '" for signing.', 0, $data);
            }
        } else {
            $input = strval($data);
        }

        if ($outputFile === null) {
            $output = '';
        } else {
            $output = @fopen($outputFile, 'wb');
            if ($output=== false) {
                if ($isFile) {
                    fclose($input);
                }
                throw new Crypt_GPG_FileException('Could not open output ' .
                    'file "' . $outputFile . '" for storing signed ' .
                    'data.', 0, $outputFile);
            }
        }

        switch ($mode) {
        case Crypt_GPG::SIGN_MODE_DETACHED:
            $operation = '--detach-sign';
            break;
        case Crypt_GPG::SIGN_MODE_CLEAR:
            $operation = '--clearsign';
            break;
        case Crypt_GPG::SIGN_MODE_NORMAL:
        default:
            $operation = '--sign';
            break;
        }

        $signedData = '';
        $arguments  = ($armor) ? array('--armor') : array();

        foreach ($this->signKeys as $key) {
            $arguments[] = '--local-user ' .
                escapeshellarg($key['fingerprint']);
        }

        $this->engine->reset();
        $this->engine->addStatusHandler(array($this, 'handleSignStatus'));
        $this->engine->setInput($input);
        $this->engine->setOutput($output);
        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();

        if ($isFile) {
            fclose($input);
        }

        if ($outputFile !== null) {
            fclose($output);
        }

        $code = $this->engine->getErrorCode();

        switch ($code) {
        case Crypt_GPG::ERROR_NONE:
            break;
        case Crypt_GPG::ERROR_KEY_NOT_FOUND:
            throw new Crypt_GPG_KeyNotFoundException(
                'Cannot sign data. Private key not found. Import the '.
                'private key before trying to sign data.', $code);
        case Crypt_GPG::ERROR_BAD_PASSPHRASE:
            throw new Crypt_GPG_BadPassphraseException(
                'Cannot sign data. Incorrect passphrase provided.', $code);
        case Crypt_GPG::ERROR_MISSING_PASSPHRASE:
            throw new Crypt_GPG_BadPassphraseException(
                'Cannot sign data. No passphrase provided.', $code);
        default:
            throw new Crypt_GPG_Exception(
                'Unknown error signing data.', $code);
        }

        if ($outputFile === null) {
            return $output;
        }
    }

    // }}}
    // {{{ _verify()

    /**
     * Verifies data
     *
     * @param string  $data      the signed data to be verified.
     * @param boolean $isFile    whether or not the data is a filename.
     * @param string  $signature if verifying a file signed using a detached
     *                           signature, this must be the detached signature
     *                           data. Otherwise, specify ''.
     *
     * @return array an array of {@link Crypt_GPG_Signature} objects for the
     *               signed data.
     *
     * @throws Crypt_GPG_NoDataException if the provided data is not signed
     *         data.
     *
     * @throws Crypt_GPG_FileException if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <i>debug</i> option and file a bug report if these
     *         exceptions occur.
     *
     * @see Crypt_GPG_Signature
     */
    private function _verify($data, $isFile, $signature)
    {
        if ($signature == '') {
            $operation = '--verify';
            $arguments = array();
        } else {
            // Signed data goes in FD_MESSAGE, detached signature data goes in
            // FD_INPUT.
            $operation = '--verify - "-&' . Crypt_GPG_Engine::FD_MESSAGE. '"';
            $arguments = array('--enable-special-filenames');
        }

        $handler = new Crypt_GPG_VerifyStatusHandler();

        if ($isFile) {
            $input = @fopen($data, 'rb');
            if ($input === false) {
                throw new Crypt_GPG_FileException('Could not open input ' .
                    'file "' . $data . '" for verifying.', 0, $data);
            }
        } else {
            $input = strval($data);
            if ($input == '') {
                throw new Crypt_GPG_NoDataException(
                    'No valid signature data found.', Crypt_GPG::ERROR_NO_DATA);
            }
        }

        $this->engine->reset();
        $this->engine->addStatusHandler(array($handler, 'handle'));

        if ($signature == '') {
            // signed or clearsigned data
            $this->engine->setInput($input);
        } else {
            // detached signature
            $this->engine->setInput($signature);
            $this->engine->setMessage($input);
        }

        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();

        if ($isFile) {
            fclose($input);
        }

        $code = $this->engine->getErrorCode();

        switch ($code) {
        case Crypt_GPG::ERROR_NONE:
            break;
        case Crypt_GPG::ERROR_NO_DATA:
            throw new Crypt_GPG_NoDataException(
                'No valid signature data found.', $code);
        case Crypt_GPG::ERROR_KEY_NOT_FOUND:
            throw new Crypt_GPG_KeyNotFoundException(
                'Public key required for data verification not in keyring.',
                $code);
        default:
            throw new Crypt_GPG_Exception(
                'Unknown error validating signature details.', $code);
        }

        return $handler->getSignatures();
    }

    // }}}
}

// }}}

?>
