pipeline {
    agent any

    stages {
        stage('Clone') {
            steps {
                git branch: 'master', url: 'https://github.com/Khadije-Taleb/projectDevOps.git'
            }
        }

        stage('Install Dependencies') {
            steps {
                sh 'composer install'
            }
        }

        stage('Run Tests') {
            steps {
                sh 'phpunit tests'
            }
        }
    }

    post {
        success {
            echo 'Build réussi ✅'
        }
        failure {
            echo 'Build échoué ❌'
        }
    }
}
