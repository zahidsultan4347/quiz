<template>
  <div class="quiz-interface">
    <!-- Timer -->
    <div class="timer" :class="timerClass">
      Time Remaining: {{ formatTime(timeRemaining) }}
    </div>
    
    <!-- Questions -->
    <div class="question-container" v-if="currentQuestion">
      <div class="question-header">
        <h3>Question {{ currentQuestionIndex + 1 }} of {{ questions.length }}</h3>
        <div class="progress">
          <div class="progress-bar" :style="{ width: progressPercentage + '%' }"></div>
        </div>
      </div>
      
      <div class="question-content">
        <div class="question-text" v-html="currentQuestion.text"></div>
        
        <div class="answers">
          <div v-for="(answer, index) in currentQuestion.answers" 
               :key="index"
               class="answer-option"
               :class="{ 'selected': selectedAnswer === index }"
               @click="selectAnswer(index)">
            {{ answer.text }}
          </div>
        </div>
      </div>
      
      <!-- Navigation -->
      <div class="navigation">
        <button @click="previousQuestion" :disabled="currentQuestionIndex === 0">
          Previous
        </button>
        <button @click="nextQuestion" :disabled="currentQuestionIndex === questions.length - 1">
          Next
        </button>
        <button @click="saveAnswer" class="btn-save">
          Save Answer
        </button>
        <button @click="autoSave" class="btn-auto-save">
          Auto Save
        </button>
      </div>
    </div>
    
    <!-- Quiz Controls -->
    <div class="quiz-controls">
      <button @click="submitQuiz" class="btn-submit">
        Submit Quiz
      </button>
      <button @click="pauseQuiz" class="btn-pause">
        Pause Quiz
      </button>
    </div>
    
    <!-- Connection Status -->
    <div class="connection-status" :class="connectionClass">
      {{ connectionStatus }}
    </div>
  </div>
</template>

<script>
export default {
  props: {
    attemptId: {
      type: Number,
      required: true
    }
  },
  
  data() {
    return {
      questions: [],
      currentQuestionIndex: 0,
      selectedAnswer: null,
      timeRemaining: 0,
      answers: {},
      isOnline: true,
      autoSaveInterval: null
    };
  },
  
  computed: {
    currentQuestion() {
      return this.questions[this.currentQuestionIndex];
    },
    
    progressPercentage() {
      return ((this.currentQuestionIndex + 1) / this.questions.length) * 100;
    },
    
    timerClass() {
      if (this.timeRemaining < 300) return 'warning';
      if (this.timeRemaining < 60) return 'critical';
      return '';
    },
    
    connectionStatus() {
      return this.isOnline ? 'Online' : 'Offline - Answers saved locally';
    },
    
    connectionClass() {
      return this.isOnline ? 'online' : 'offline';
    }
  },
  
  mounted() {
    this.loadQuiz();
    this.startTimer();
    this.setupAutoSave();
    this.setupConnectionMonitoring();
  },
  
  beforeUnmount() {
    clearInterval(this.autoSaveInterval);
  },
  
  methods: {
    async loadQuiz() {
      try {
        const response = await axios.get(`/quiz/attempt/${this.attemptId}`);
        this.questions = response.data.attempt.questions || [];
        this.timeRemaining = response.data.attempt.time_remaining;
        this.answers = response.data.attempt.answers || {};
      } catch (error) {
        console.error('Failed to load quiz:', error);
      }
    },
    
    startTimer() {
      setInterval(() => {
        if (this.timeRemaining > 0) {
          this.timeRemaining--;
          
          // Auto-submit when time is up
          if (this.timeRemaining === 0) {
            this.submitQuiz();
          }
        }
      }, 1000);
    },
    
    formatTime(seconds) {
      const minutes = Math.floor(seconds / 60);
      const secs = seconds % 60;
      return `${minutes}:${secs.toString().padStart(2, '0')}`;
    },
    
    selectAnswer(index) {
      this.selectedAnswer = index;
    },
    
    async saveAnswer() {
      if (this.selectedAnswer === null) return;
      
      try {
        await axios.post(`/quiz/attempt/${this.attemptId}/answer`, {
          question_id: this.currentQuestion.id,
          answer: this.selectedAnswer,
          sequence_number: this.currentQuestionIndex,
          time_spent: 0 // Calculate actual time spent
        });
        
        // Update local answers
        this.answers[this.currentQuestion.id] = this.selectedAnswer;
      } catch (error) {
        console.error('Failed to save answer:', error);
      }
    },
    
    async autoSave() {
      try {
        await axios.post(`/quiz/attempt/${this.attemptId}/auto-save`, {
          answers: this.answers,
          current_question: this.currentQuestionIndex,
          time_remaining: this.timeRemaining
        });
      } catch (error) {
        console.error('Auto-save failed:', error);
        // Save locally if offline
        localStorage.setItem(`quiz_${this.attemptId}`, JSON.stringify({
          answers: this.answers,
          timestamp: new Date().toISOString()
        }));
      }
    },
    
    setupAutoSave() {
      this.autoSaveInterval = setInterval(() => {
        this.autoSave();
      }, 30000); // Auto-save every 30 seconds
    },
    
    setupConnectionMonitoring() {
      window.addEventListener('online', () => {
        this.isOnline = true;
        this.syncOfflineData();
      });
      
      window.addEventListener('offline', () => {
        this.isOnline = false;
      });
    },
    
    async syncOfflineData() {
      const offlineData = localStorage.getItem(`quiz_${this.attemptId}`);
      if (offlineData) {
        try {
          const data = JSON.parse(offlineData);
          await this.autoSave();
          localStorage.removeItem(`quiz_${this.attemptId}`);
        } catch (error) {
          console.error('Failed to sync offline data:', error);
        }
      }
    },
    
    previousQuestion() {
      if (this.currentQuestionIndex > 0) {
        this.currentQuestionIndex--;
        this.loadQuestionAnswers();
      }
    },
    
    nextQuestion() {
      if (this.currentQuestionIndex < this.questions.length - 1) {
        this.currentQuestionIndex++;
        this.loadQuestionAnswers();
      }
    },
    
    loadQuestionAnswers() {
      const questionId = this.currentQuestion.id;
      this.selectedAnswer = this.answers[questionId] || null;
    },
    
    async submitQuiz() {
      if (confirm('Are you sure you want to submit the quiz?')) {
        try {
          const response = await axios.post(`/quiz/attempt/${this.attemptId}/submit`);
          alert('Quiz submitted successfully!');
          window.location.href = `/quiz/results/${this.attemptId}`;
        } catch (error) {
          console.error('Failed to submit quiz:', error);
          alert('Failed to submit quiz. Please try again.');
        }
      }
    },
    
    async pauseQuiz() {
      try {
        await axios.post(`/quiz/attempt/${this.attemptId}/pause`);
        alert('Quiz paused. You can resume later.');
        window.location.href = '/dashboard';
      } catch (error) {
        console.error('Failed to pause quiz:', error);
      }
    }
  }
};
</script>

<style scoped>
.quiz-interface {
  max-width: 800px;
  margin: 0 auto;
  padding: 20px;
}

.timer {
  font-size: 1.5em;
  font-weight: bold;
  text-align: center;
  padding: 15px;
  background: #4CAF50;
  color: white;
  border-radius: 5px;
  margin-bottom: 20px;
}

.timer.warning {
  background: #ff9800;
  animation: pulse 1s infinite;
}

.timer.critical {
  background: #f44336;
  animation: pulse 0.5s infinite;
}

@keyframes pulse {
  0% { opacity: 1; }
  50% { opacity: 0.7; }
  100% { opacity: 1; }
}

.question-container {
  background: white;
  padding: 30px;
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.question-header {
  margin-bottom: 20px;
}

.progress {
  height: 10px;
  background: #e0e0e0;
  border-radius: 5px;
  overflow: hidden;
  margin-top: 10px;
}

.progress-bar {
  height: 100%;
  background: #4CAF50;
  transition: width 0.3s;
}

.question-content {
  margin: 20px 0;
}

.answers {
  margin: 20px 0;
}

.answer-option {
  padding: 15px;
  margin: 10px 0;
  border: 2px solid #ddd;
  border-radius: 5px;
  cursor: pointer;
  transition: all 0.3s;
}

.answer-option:hover {
  background: #f5f5f5;
}

.answer-option.selected {
  border-color: #4CAF50;
  background: #e8f5e9;
}

.navigation {
  display: flex;
  justify-content: space-between;
  margin-top: 30px;
}

button {
  padding: 10px 20px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 16px;
}

.btn-save {
  background: #2196F3;
  color: white;
}

.btn-auto-save {
  background: #FF9800;
  color: white;
}

.btn-submit {
  background: #4CAF50;
  color: white;
  padding: 15px 30px;
  font-size: 18px;
}

.btn-pause {
  background: #9C27B0;
  color: white;
}

.quiz-controls {
  display: flex;
  justify-content: space-between;
  margin-top: 20px;
}

.connection-status {
  position: fixed;
  bottom: 20px;
  right: 20px;
  padding: 10px 20px;
  border-radius: 5px;
  font-weight: bold;
}

.connection-status.online {
  background: #4CAF50;
  color: white;
}

.connection-status.offline {
  background: #f44336;
  color: white;
}
</style>