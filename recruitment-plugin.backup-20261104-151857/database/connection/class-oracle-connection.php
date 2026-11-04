<?php

/**
 * Provides the shared, lazy Oracle PDO connection for this request.
 */
class Recruitment_Oracle_Connection {

    private static ?PDO $connection = null;

    /** @var array<string, string> */
    private array $config;

    /**
     * @param array<string, string> $config Optional configuration overrides.
     */
    public function __construct( array $config = [] ) {
        $this->config = array_merge( $this->get_default_config(), $config );
    }

    public function is_configured(): bool {
        return '' !== $this->config['username'] && '' !== $this->config['password'] && '' !== $this->config['dsn'];
    }

    public function get_connection(): PDO {
        if ( null !== self::$connection ) {
            return self::$connection;
        }

        if ( ! $this->is_configured() ) {
            throw new RuntimeException( 'Oracle database configuration is incomplete.' );
        }
        if ( ! class_exists( 'PDO' ) || ! in_array( 'oci', PDO::getAvailableDrivers(), true ) ) {
            throw new RuntimeException( 'The PDO_OCI driver is not available.' );
        }

        self::$connection = new PDO(
            $this->config['dsn'],
            $this->config['username'],
            $this->config['password'],
            [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ]
        );

        return self::$connection;
    }

    /** @return array<string, string> */
    private function get_default_config(): array {
        $options = function_exists( 'get_option' ) ? (array) get_option( 'recruitment_oracle_config', [] ) : [];
        $dotenv = $this->load_dotenv_config();
        $config = [];

        foreach ( [ 'dsn' => 'RECRUITMENT_ORACLE_DSN', 'username' => 'RECRUITMENT_ORACLE_USER', 'password' => 'RECRUITMENT_ORACLE_PASSWORD' ] as $key => $name ) {
            if ( defined( $name ) ) {
                $config[ $key ] = (string) constant( $name );
                continue;
            }

            $environment_value = getenv( $name );
            if ( false !== $environment_value && '' !== $environment_value ) {
                $config[ $key ] = $environment_value;
                continue;
            }

            $config[ $key ] = (string) ( $dotenv[ $name ] ?? $options[ $key ] ?? '' );
        }

        return $config;
    }

    /** @return array<string, string> */
    private function load_dotenv_config(): array {
        $path = defined( 'RECRUITMENT_DOTENV_PATH' )
            ? (string) RECRUITMENT_DOTENV_PATH
            : (string) ( getenv( 'RECRUITMENT_DOTENV_PATH' ) ?: '' );

        if ( '' === $path ) {
            return [];
        }

        if ( ! is_file( $path ) || ! is_readable( $path ) ) {
            error_log( 'Recruitment Oracle configuration file is missing or unreadable.' );
            return [];
        }

        $lines = file( $path, FILE_IGNORE_NEW_LINES );
        if ( false === $lines ) {
            error_log( 'Recruitment Oracle configuration file could not be read.' );
            return [];
        }

        $allowed_names = [ 'RECRUITMENT_ORACLE_DSN', 'RECRUITMENT_ORACLE_USER', 'RECRUITMENT_ORACLE_PASSWORD' ];
        $config = [];

        foreach ( $lines as $line_number => $line ) {
            if ( 0 === $line_number ) {
                $line = preg_replace( '/^\xEF\xBB\xBF/', '', $line );
            }

            $line = trim( $line );
            if ( '' === $line || '#' === $line[0] ) {
                continue;
            }

            if ( ! preg_match( '/^(?:export\s+)?([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $matches ) ) {
                error_log( 'Recruitment Oracle configuration file has invalid syntax on line ' . ( $line_number + 1 ) . '.' );
                return [];
            }

            if ( ! in_array( $matches[1], $allowed_names, true ) ) {
                continue;
            }

            $value = trim( $matches[2] );
            if ( '"' === ( $value[0] ?? '' ) || "'" === ( $value[0] ?? '' ) ) {
                $quote = $value[0];
                if ( strlen( $value ) < 2 || $quote !== $value[ strlen( $value ) - 1 ] ) {
                    error_log( 'Recruitment Oracle configuration file has an unterminated value on line ' . ( $line_number + 1 ) . '.' );
                    return [];
                }
                $value = substr( $value, 1, -1 );
            } else {
                $value = preg_replace( '/\s+#.*$/', '', $value );
                $value = trim( (string) $value );
            }

            $config[ $matches[1] ] = $value;
        }

        return $config;
    }
}
