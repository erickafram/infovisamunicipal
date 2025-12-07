                <!-- Opções de Menu para Usuários -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item me-2">
                        <a class="nav-link" href="/visamunicipal/views/meus_relatos.php">
                            <i class="bi bi-chat-left-text me-1"></i> Meus Relatos 
                            <?php
                            // Verifica se existem novas respostas para exibir badge
                            if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
                                // Verifica se as colunas de resposta existem
                                $checkColumnsStmt = $conn->prepare("SHOW COLUMNS FROM relatos_usuarios LIKE 'resposta'");
                                $checkColumnsStmt->execute();
                                $columnsExist = ($checkColumnsStmt->get_result()->num_rows > 0);
                                
                                if ($columnsExist) {
                                    $usuario_id = $_SESSION['user']['id'];
                                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM relatos_usuarios 
                                                       WHERE usuario_externo_id = ? AND resposta IS NOT NULL 
                                                       AND data_resposta > (NOW() - INTERVAL 7 DAY)");
                                    $stmt->bind_param("i", $usuario_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    $novas_respostas = $result->fetch_assoc()['count'];
                                    
                                    if ($novas_respostas > 0) {
                                        echo '<span class="badge bg-danger rounded-pill">' . $novas_respostas . '</span>';
                                    }
                                }
                            }
                            ?>
                        </a>
                    </li>
                    <li class="nav-item me-2">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#helpModal">
                            <i class="bi bi-question-circle me-1"></i> Ajuda
                        </a>
                    </li>
                </ul> 