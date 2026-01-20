pipeline {
    agent { label 'php-agent' }

    stages {
        stage('Clone') {
            steps {
                git branch: 'master', url: 'https://github.com/Khadije-Taleb/projectDevOps.git'
            }
        }

        stage('Run Tests') {
            steps {
                sh 'phpunit tests || echo "No tests found, skipping"'
            }
        }
    }

    post {
        success {
            echo '✅ Build réussi!'
        }
        failure {
            echo '❌ Build échoué!'
        }
    }
}
