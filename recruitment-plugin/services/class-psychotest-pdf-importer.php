<?php

/**
 * Extract and validate question text from a text-based PDF before HR review.
 */
class Recruitment_Psychotest_Pdf_Importer {

    public const MAX_FILE_BYTES = 15 * 1024 * 1024;
    public const MAX_QUESTIONS_PER_IMPORT = 200;

    /**
     * @return array{success: bool, text?: string, error?: string}
     */
    public function extract_text( string $path ): array {
        if ( ! class_exists( \Smalot\PdfParser\Parser::class ) ) {
            return [ 'success' => false, 'error' => 'Parser PDF belum tersedia. Pasang dependensi Composer plugin terlebih dahulu.' ];
        }
        $file_size = is_readable( $path ) ? filesize( $path ) : false;
        if ( false === $file_size || self::MAX_FILE_BYTES < $file_size ) {
            return [ 'success' => false, 'error' => 'File PDF tidak dapat dibaca atau melebihi batas 15 MB.' ];
        }

        try {
            $pdf = ( new \Smalot\PdfParser\Parser() )->parseFile( $path );
            if ( 100 < count( $pdf->getPages() ) ) {
                return [ 'success' => false, 'error' => 'PDF melebihi batas 100 halaman.' ];
            }
            $text = trim( str_replace( "\f", "\n", $pdf->getText() ) );
        } catch ( \Exception $exception ) {
            error_log( 'Recruitment question PDF extraction failed: ' . get_class( $exception ) );
            return [ 'success' => false, 'error' => 'PDF tidak dapat dibaca. Periksa file lalu coba kembali.' ];
        }

        if ( '' === $text ) {
            return [ 'success' => false, 'error' => 'PDF tidak memiliki teks yang dapat diekstrak. PDF hasil scan belum didukung.' ];
        }
        if ( 150000 < strlen( $text ) ) {
            return [ 'success' => false, 'error' => 'Teks PDF melebihi batas 150 KB.' ];
        }

        $has_name = 1 === preg_match( '/\b(?:nama|name)\b/iu', $text );
        $report_markers = [
            '/\b(?:tanggal\s+(?:tes|test)|test\s+date|tanggal\s+lahir)\b/iu',
            '/\b(?:jenis\s+kelamin|gender|usia|umur)\b/iu',
            '/\b(?:hasil|skor|score|nilai|profil\s+kepribadian|kesimpulan)\b/iu',
        ];
        $report_marker_count = count(
            array_filter(
                $report_markers,
                static fn ( string $pattern ): bool => 1 === preg_match( $pattern, $text )
            )
        );
        if ( ( $has_name && 0 < $report_marker_count ) || 2 <= $report_marker_count ) {
            return [ 'success' => false, 'error' => 'PDF tampaknya berisi identitas atau hasil tes peserta, bukan naskah soal. File tidak diproses.' ];
        }

        $text = preg_replace( '/\r\n?/', "\n", $text ) ?? $text;
        $text = preg_replace( "/\n{3,}/", "\n\n", $text ) ?? $text;

        return [ 'success' => true, 'text' => trim( $text ) ];
    }

    /**
     * @return array{success: bool, questions?: array<int, array<string, mixed>>, error?: string}
     */
    public function parse_questions( string $text, string $test, string $subtest = '' ): array {
        if ( 'iq' === $test ) {
            return $this->parse_iq_questions( $text, $subtest );
        }
        if ( ! in_array( $test, [ 'disc', 'papi' ], true ) ) {
            return [ 'success' => false, 'error' => 'Impor PDF hanya tersedia untuk IQ, DISC, dan PAPI Kostick.' ];
        }

        $expected_items = 'disc' === $test ? 4 : 2;
        $blocks = preg_split( '/\n\s*\n/u', trim( $text ) ) ?: [];
        if ( 1 === count( $blocks ) ) {
            $lines = preg_split( '/\R/u', trim( $text ) ) ?: [];
            $lines = array_values( array_filter( array_map( 'trim', $lines ), static fn ( string $line ): bool => '' !== $line ) );
            if ( $lines && 0 === count( $lines ) % $expected_items ) {
                $blocks = array_chunk( $lines, $expected_items );
                $blocks = array_map( static fn ( array $items ): string => implode( "\n", $items ), $blocks );
            }
        }
        $questions = [];
        foreach ( $blocks as $block ) {
            $items = preg_split( '/\R/u', trim( $block ) ) ?: [];
            $items = array_values(
                array_filter(
                    array_map(
                        static function ( string $item ): string {
                            $item = trim( $item );
                            $item = preg_replace( '/^\s*(?:(?:\d{1,3}[.)])|[-*])\s*/u', '', $item ) ?? $item;
                            return trim( $item );
                        },
                        $items
                    ),
                    static fn ( string $item ): bool => '' !== $item
                )
            );
            if ( ! $items ) {
                continue;
            }
            if ( $expected_items !== count( $items ) ) {
                return [
                    'success' => false,
                    'error' => sprintf(
                        'Setiap grup %s harus berisi tepat %d pernyataan. Gunakan satu pernyataan per baris dan edit teks hasil ekstraksi sebelum menyimpan.',
                        'disc' === $test ? 'DISC' : 'PAPI Kostick',
                        $expected_items
                    ),
                ];
            }
            $questions[] = [ 'items' => $items ];
        }

        if ( ! $questions || self::MAX_QUESTIONS_PER_IMPORT < count( $questions ) ) {
            return [ 'success' => false, 'error' => sprintf( 'PDF harus menghasilkan 1 sampai %d grup soal.', self::MAX_QUESTIONS_PER_IMPORT ) ];
        }

        foreach ( $questions as $question ) {
            $encoded = wp_json_encode( $question['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            if ( ! is_string( $encoded ) || 4000 < strlen( $encoded ) ) {
                return [ 'success' => false, 'error' => 'Satu grup pernyataan melebihi batas panjang soal.' ];
            }
        }

        return [ 'success' => true, 'questions' => $questions ];
    }

    /**
     * @return array{success: bool, questions?: array<int, array<string, mixed>>, error?: string}
     */
    private function parse_iq_questions( string $text, string $subtest ): array {
        if ( ! in_array( $subtest, [ 'iq_numbers', 'iq_assembly', 'iq_words', 'iq_abstraction' ], true ) ) {
            return [ 'success' => false, 'error' => 'Pilih salah satu dari empat subtes IQ sebelum memproses PDF.' ];
        }

        $text = trim( preg_replace( '/\r\n?/', "\n", $text ) ?? $text );
        if ( '' === $text ) {
            return [ 'success' => false, 'error' => 'Tidak ada teks soal IQ untuk diproses.' ];
        }

        $blocks = preg_split( '/\n\s*\n/u', $text ) ?: [];
        if ( 1 === count( $blocks ) ) {
            $numbered_blocks = preg_split( '/(?=^\s*\d{1,3}[.)]\s+\S)/mu', $text ) ?: [];
            if ( 1 < count( $numbered_blocks ) ) {
                $blocks = $numbered_blocks;
            }
        }

        $questions = [];
        foreach ( $blocks as $block ) {
            $prompt_lines = [];
            $choices = [];
            $answer_key = '';
            $last_choice = null;
            foreach ( preg_split( '/\n/u', trim( $block ) ) ?: [] as $line ) {
                $line = trim( $line );
                if ( '' === $line ) {
                    continue;
                }
                if ( preg_match( '/^(?:kunci(?:\s+jawaban)?|jawaban(?:\s+benar)?|answer|correct\s+answer)\s*[:=-]\s*([A-F](?:\s*[,|]\s*[A-F])*)\s*$/iu', $line, $matches ) ) {
                    $answer_key = strtoupper( preg_replace( '/\s+/u', '', $matches[1] ) ?? $matches[1] );
                    $last_choice = null;
                    continue;
                }
                if ( preg_match( '/^([A-F])[.) :]\s*(.+)$/iu', $line, $matches ) ) {
                    $label = strtoupper( $matches[1] );
                    if ( isset( $choices[ $label ] ) ) {
                        return [ 'success' => false, 'error' => 'Label pilihan jawaban pada soal IQ tidak boleh berulang.' ];
                    }
                    $choices[ $label ] = trim( $matches[2] );
                    $last_choice = $label;
                    continue;
                }
                if ( preg_match( '/^\d{1,3}[.)]\s+(.+)$/u', $line, $matches ) && ! $prompt_lines && ! $choices ) {
                    $line = trim( $matches[1] );
                }
                if ( null !== $last_choice ) {
                    $choices[ $last_choice ] .= ' ' . $line;
                } else {
                    $prompt_lines[] = $line;
                }
            }

            if ( ! $prompt_lines && ! $choices && '' === $answer_key ) {
                continue;
            }
            $prompt = trim( implode( "\n", $prompt_lines ) );
            if ( '' === $prompt || 4000 < strlen( $prompt ) ) {
                return [ 'success' => false, 'error' => 'Setiap soal IQ harus memiliki pertanyaan dengan panjang maksimal 4.000 karakter.' ];
            }
            if ( 2 > count( $choices ) || 6 < count( $choices ) || '' === $answer_key ) {
                return [ 'success' => false, 'error' => 'Setiap soal IQ harus memiliki 2–6 pilihan A–F dan kunci jawaban (contoh: Kunci: B).' ];
            }
            foreach ( $choices as $choice ) {
                if ( '' === trim( $choice ) ) {
                    return [ 'success' => false, 'error' => 'Pilihan jawaban IQ tidak boleh kosong.' ];
                }
            }
            $labels = array_keys( $choices );
            foreach ( str_split( str_replace( ',', '|', $answer_key ) ) as $answer ) {
                if ( '|' === $answer ) {
                    continue;
                }
                if ( ! in_array( $answer, $labels, true ) ) {
                    return [ 'success' => false, 'error' => 'Kunci jawaban IQ harus cocok dengan label pilihan yang tersedia.' ];
                }
            }
            $answers = array_values( array_filter( preg_split( '/[,|]/', $answer_key ) ?: [] ) );
            if ( ! $answers || 2 < count( $answers ) || count( $answers ) !== count( array_unique( $answers ) ) ) {
                return [ 'success' => false, 'error' => 'Kunci jawaban IQ tidak valid.' ];
            }
            $answer_indexes = array_map(
                static fn ( string $answer ): string => (string) ( array_search( $answer, $labels, true ) + 1 ),
                $answers
            );
            $questions[] = [
                'subtest' => $subtest,
                'prompt' => $prompt,
                'choices' => array_values( $choices ),
                'type' => 1 === count( $answer_indexes ) ? 'multiple_choice' : 'multi_select',
                'answer' => implode( '|', $answer_indexes ),
            ];
        }

        if ( ! $questions || self::MAX_QUESTIONS_PER_IMPORT < count( $questions ) ) {
            return [ 'success' => false, 'error' => sprintf( 'PDF harus menghasilkan 1 sampai %d soal IQ.', self::MAX_QUESTIONS_PER_IMPORT ) ];
        }

        return [ 'success' => true, 'questions' => $questions ];
    }
}
